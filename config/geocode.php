<?php
function geocoder_ville($ville) {
    $ville_encodee = urlencode($ville . ', France');
    $url = "https://nominatim.openstreetmap.org/search?q={$ville_encodee}&format=json&limit=1";

    $context = stream_context_create([
        'http' => [
            'header' => "User-Agent: KindleBloom/1.0 (hello@kindlebloom.fr)\r\n",
            'timeout' => 5
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if (!$response) {
        return null;
    }

    $data = json_decode($response, true);

    if (empty($data)) {
        return null;
    }

    return [
        'latitude'  => (float) $data[0]['lat'],
        'longitude' => (float) $data[0]['lon']
    ];
}

function distance_km($lat1, $lon1, $lat2, $lon2) {
    $rayon = 6371;

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return round($rayon * $c, 1);
}