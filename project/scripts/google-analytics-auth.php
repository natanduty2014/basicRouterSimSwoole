<?php
/**
 * One-time script to obtain a Google OAuth2 refresh_token for the
 * Google Analytics Data API (GA4).
 *
 * Usage:
 *   php google-analytics-auth.php
 *
 * 1. Opens (or prints) a consent URL — authorize in your browser.
 * 2. Google redirects to http://localhost:8888 with an auth code.
 * 3. The script exchanges the code for tokens and prints the refresh_token.
 * 4. Copy the refresh_token into your .env as GOOGLE_ANALYTICS_REFRESH_TOKEN.
 */

$clientId     = getenv('GOOGLE_ANALYTICS_CLIENT_ID') ?: '';
$clientSecret = getenv('GOOGLE_ANALYTICS_CLIENT_SECRET') ?: '';
$redirectUri  = 'http://localhost:8888';
$scope        = 'https://www.googleapis.com/auth/analytics.readonly';

if ($clientId === '' || $clientSecret === '') {
    die("Missing GOOGLE_ANALYTICS_CLIENT_ID or GOOGLE_ANALYTICS_CLIENT_SECRET in the environment.\n");
}

$authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id'     => $clientId,
    'redirect_uri'  => $redirectUri,
    'response_type' => 'code',
    'scope'         => $scope,
    'access_type'   => 'offline',
    'prompt'        => 'consent',
]);

echo "\n=== Google Analytics OAuth2 Setup ===\n\n";
echo "1) Open this URL in your browser:\n\n";
echo "   $authUrl\n\n";
echo "2) Authorize the application.\n";
echo "3) You'll be redirected to localhost:8888 — the script will capture the code.\n\n";
echo "Starting local server on http://localhost:8888 ...\n\n";

$socket = stream_socket_server('tcp://127.0.0.1:8888', $errno, $errstr);
if (!$socket) {
    die("Could not start server: $errstr ($errno)\n");
}

$conn = stream_socket_accept($socket, 120);
if (!$conn) {
    fclose($socket);
    die("Timeout waiting for redirect.\n");
}

$request = fread($conn, 8192);
preg_match('/GET \/\?code=([^ &]+)/', $request, $matches);
$code = urldecode($matches[1] ?? '');

$html = $code
    ? '<html><body><h2>Authorization received!</h2><p>You can close this tab and return to the terminal.</p></body></html>'
    : '<html><body><h2>Error: no code received.</h2></body></html>';

fwrite($conn, "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: " . strlen($html) . "\r\n\r\n" . $html);
fclose($conn);
fclose($socket);

if (!$code) {
    die("No authorization code received.\n");
}

echo "Authorization code received. Exchanging for tokens...\n\n";

$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code',
]));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode !== 200 || !isset($data['refresh_token'])) {
    echo "Error exchanging code for tokens:\n";
    echo json_encode($data, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

echo "=== SUCCESS ===\n\n";
echo "refresh_token:\n";
echo $data['refresh_token'] . "\n\n";
echo "Add this to your .env file:\n\n";
echo "GOOGLE_ANALYTICS_REFRESH_TOKEN=" . $data['refresh_token'] . "\n\n";
echo "Also add your GA4 Property ID:\n";
echo "GOOGLE_ANALYTICS_PROPERTY_ID=<your-property-id>\n\n";
