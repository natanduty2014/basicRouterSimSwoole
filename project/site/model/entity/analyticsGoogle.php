<?php

namespace App\model\entity;

use Functions\db\redis;

class analyticsGoogle
{
    private static string $tokenUrl = 'https://oauth2.googleapis.com/token';
    private static string $gaBaseUrl = 'https://analyticsdata.googleapis.com/v1beta/properties';

    /**
     * Fetch all analytics data for the given date range.
     * Returns the full structure expected by the frontend AnalyticsData type.
     */
    public static function getData(string $startDate, string $endDate): array
    {
        $cacheKey = "ga_analytics_{$startDate}_{$endDate}";
        $cached = redis::get($cacheKey);
        if ($cached) {
            $decoded = json_decode($cached, true);
            if ($decoded) {
                return ['status' => 200, 'data' => $decoded];
            }
        }

        $propertyId = getenv('GOOGLE_ANALYTICS_PROPERTY_ID');
        if (!$propertyId) {
            return ['status' => 500, 'message' => 'GOOGLE_ANALYTICS_PROPERTY_ID not configured.'];
        }

        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            return ['status' => 500, 'message' => 'Failed to obtain Google access token.'];
        }

        $reportUrl = self::$gaBaseUrl . '/' . $propertyId . ':runReport';

        $kpis         = self::fetchKPIs($reportUrl, $accessToken, $startDate, $endDate);
        $dailyMetrics = self::fetchDailyMetrics($reportUrl, $accessToken, $startDate, $endDate);
        $traffic      = self::fetchTrafficSources($reportUrl, $accessToken, $startDate, $endDate);
        $topPages     = self::fetchTopPages($reportUrl, $accessToken, $startDate, $endDate);
        $devices      = self::fetchDevices($reportUrl, $accessToken, $startDate, $endDate);
        $geo          = self::fetchGeoLocations($reportUrl, $accessToken, $startDate, $endDate);

        $result = [
            'period' => [
                'startDate' => $startDate,
                'endDate'   => $endDate,
            ],
            'kpis'           => $kpis,
            'dailyMetrics'   => $dailyMetrics,
            'trafficSources' => $traffic,
            'topPages'       => $topPages,
            'devices'        => $devices,
            'geoLocations'   => $geo,
        ];

        redis::saveEx($cacheKey, json_encode($result), 300);

        return ['status' => 200, 'data' => $result];
    }

    // -- Token management -------------------------------------------------------

    private static function getAccessToken(): ?string
    {
        $cached = redis::get('ga_access_token');
        if ($cached) {
            return $cached;
        }

        $refreshToken = getenv('GOOGLE_ANALYTICS_REFRESH_TOKEN');
        $clientId     = getenv('GOOGLE_ANALYTICS_CLIENT_ID');
        $clientSecret = getenv('GOOGLE_ANALYTICS_CLIENT_SECRET');

        if (!$refreshToken || !$clientId || !$clientSecret) {
            error_log('[GA] Missing Google Analytics OAuth env vars.');
            return null;
        }

        $ch = curl_init(self::$tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type'    => 'refresh_token',
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log('[GA] Token refresh failed: ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        $token = $data['access_token'] ?? null;
        $expiresIn = ($data['expires_in'] ?? 3600) - 120; // 2-minute safety margin

        if ($token) {
            redis::saveEx('ga_access_token', $token, max($expiresIn, 60));
        }

        return $token;
    }

    // -- GA4 Report Helpers -----------------------------------------------------

    private static function runReport(string $url, string $token, array $body): ?array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("[GA] runReport failed ($httpCode): $response");
            return null;
        }

        return json_decode($response, true);
    }

    private static function baseDateRange(string $startDate, string $endDate): array
    {
        return [['startDate' => $startDate, 'endDate' => $endDate]];
    }

    // -- Individual report fetchers ---------------------------------------------

    private static function fetchKPIs(string $url, string $token, string $start, string $end): array
    {
        $body = [
            'dateRanges' => self::baseDateRange($start, $end),
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'newUsers'],
                ['name' => 'screenPageViews'],
                ['name' => 'bounceRate'],
                ['name' => 'averageSessionDuration'],
            ],
        ];

        $result = self::runReport($url, $token, $body);
        $row = $result['rows'][0]['metricValues'] ?? [];

        return [
            'sessions'           => (int)($row[0]['value'] ?? 0),
            'users'              => (int)($row[1]['value'] ?? 0),
            'newUsers'           => (int)($row[2]['value'] ?? 0),
            'pageviews'          => (int)($row[3]['value'] ?? 0),
            'bounceRate'         => round((float)($row[4]['value'] ?? 0) * 100, 2),
            'avgSessionDuration' => round((float)($row[5]['value'] ?? 0), 1),
        ];
    }

    private static function fetchDailyMetrics(string $url, string $token, string $start, string $end): array
    {
        $body = [
            'dateRanges' => self::baseDateRange($start, $end),
            'dimensions' => [['name' => 'date']],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'totalUsers'],
                ['name' => 'screenPageViews'],
            ],
            'orderBys' => [
                ['dimension' => ['dimensionName' => 'date', 'orderType' => 'ALPHANUMERIC']],
            ],
        ];

        $result = self::runReport($url, $token, $body);
        $rows = $result['rows'] ?? [];
        $output = [];

        foreach ($rows as $row) {
            $raw = $row['dimensionValues'][0]['value'] ?? '';
            $dateFormatted = substr($raw, 0, 4) . '-' . substr($raw, 4, 2) . '-' . substr($raw, 6, 2);
            $output[] = [
                'date'      => $dateFormatted,
                'sessions'  => (int)($row['metricValues'][0]['value'] ?? 0),
                'users'     => (int)($row['metricValues'][1]['value'] ?? 0),
                'pageviews' => (int)($row['metricValues'][2]['value'] ?? 0),
            ];
        }

        return $output;
    }

    private static function fetchTrafficSources(string $url, string $token, string $start, string $end): array
    {
        $body = [
            'dateRanges' => self::baseDateRange($start, $end),
            'dimensions' => [['name' => 'sessionSource']],
            'metrics'    => [['name' => 'sessions']],
            'orderBys'   => [
                ['metric' => ['metricName' => 'sessions'], 'desc' => true],
            ],
            'limit' => 10,
        ];

        $result = self::runReport($url, $token, $body);
        $rows = $result['rows'] ?? [];
        $total = 0;

        foreach ($rows as $row) {
            $total += (int)($row['metricValues'][0]['value'] ?? 0);
        }

        $output = [];
        foreach ($rows as $row) {
            $sessions = (int)($row['metricValues'][0]['value'] ?? 0);
            $output[] = [
                'source'     => $row['dimensionValues'][0]['value'] ?? '(unknown)',
                'sessions'   => $sessions,
                'percentage' => $total > 0 ? round(($sessions / $total) * 100, 1) : 0,
            ];
        }

        return $output;
    }

    private static function fetchTopPages(string $url, string $token, string $start, string $end): array
    {
        $body = [
            'dateRanges' => self::baseDateRange($start, $end),
            'dimensions' => [
                ['name' => 'pagePath'],
                ['name' => 'pageTitle'],
            ],
            'metrics' => [
                ['name' => 'screenPageViews'],
                ['name' => 'averageSessionDuration'],
                ['name' => 'bounceRate'],
            ],
            'orderBys' => [
                ['metric' => ['metricName' => 'screenPageViews'], 'desc' => true],
            ],
            'limit' => 10,
        ];

        $result = self::runReport($url, $token, $body);
        $rows = $result['rows'] ?? [];
        $output = [];

        foreach ($rows as $row) {
            $output[] = [
                'path'          => $row['dimensionValues'][0]['value'] ?? '/',
                'title'         => $row['dimensionValues'][1]['value'] ?? '',
                'pageviews'     => (int)($row['metricValues'][0]['value'] ?? 0),
                'avgTimeOnPage' => round((float)($row['metricValues'][1]['value'] ?? 0), 1),
                'bounceRate'    => round((float)($row['metricValues'][2]['value'] ?? 0) * 100, 1),
            ];
        }

        return $output;
    }

    private static function fetchDevices(string $url, string $token, string $start, string $end): array
    {
        $body = [
            'dateRanges' => self::baseDateRange($start, $end),
            'dimensions' => [['name' => 'deviceCategory']],
            'metrics'    => [['name' => 'sessions']],
            'orderBys'   => [
                ['metric' => ['metricName' => 'sessions'], 'desc' => true],
            ],
        ];

        $result = self::runReport($url, $token, $body);
        $rows = $result['rows'] ?? [];
        $total = 0;

        foreach ($rows as $row) {
            $total += (int)($row['metricValues'][0]['value'] ?? 0);
        }

        $output = [];
        foreach ($rows as $row) {
            $sessions = (int)($row['metricValues'][0]['value'] ?? 0);
            $output[] = [
                'category'   => $row['dimensionValues'][0]['value'] ?? 'unknown',
                'sessions'   => $sessions,
                'percentage' => $total > 0 ? round(($sessions / $total) * 100, 1) : 0,
            ];
        }

        return $output;
    }

    private static function fetchGeoLocations(string $url, string $token, string $start, string $end): array
    {
        $body = [
            'dateRanges' => self::baseDateRange($start, $end),
            'dimensions' => [
                ['name' => 'country'],
                ['name' => 'city'],
            ],
            'metrics' => [['name' => 'sessions']],
            'orderBys' => [
                ['metric' => ['metricName' => 'sessions'], 'desc' => true],
            ],
            'limit' => 15,
        ];

        $result = self::runReport($url, $token, $body);
        $rows = $result['rows'] ?? [];
        $total = 0;

        foreach ($rows as $row) {
            $total += (int)($row['metricValues'][0]['value'] ?? 0);
        }

        $output = [];
        foreach ($rows as $row) {
            $sessions = (int)($row['metricValues'][0]['value'] ?? 0);
            $city = $row['dimensionValues'][1]['value'] ?? '';
            $output[] = [
                'country'    => $row['dimensionValues'][0]['value'] ?? '(unknown)',
                'city'       => ($city === '(not set)') ? null : $city,
                'sessions'   => $sessions,
                'percentage' => $total > 0 ? round(($sessions / $total) * 100, 1) : 0,
            ];
        }

        return $output;
    }
}
