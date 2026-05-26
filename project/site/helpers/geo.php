<?php

namespace App\helpers;

class geo
{
    /**
     * Testa se um ponto (lat, lng) está dentro de um polígono usando ray-casting.
     *
     * @param float $lat
     * @param float $lng
     * @param array $polygon Array de [lat, lng] pairs. Polígono fechado (último = primeiro).
     * @return bool
     */
    public static function pointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $n = count($polygon);
        if ($n < 3) return false;

        $inside = false;
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $latI = (float)($polygon[$i][0] ?? 0);
            $lngI = (float)($polygon[$i][1] ?? 0);
            $latJ = (float)($polygon[$j][0] ?? 0);
            $lngJ = (float)($polygon[$j][1] ?? 0);

            $intersect = (($lngI > $lng) !== ($lngJ > $lng))
                && ($lat < ($latJ - $latI) * ($lng - $lngI) / (($lngJ - $lngI) ?: 1e-12) + $latI);

            if ($intersect) $inside = !$inside;
        }
        return $inside;
    }

    /**
     * Distância em km entre dois pontos (Haversine).
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
