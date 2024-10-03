<?php

namespace App\Traits;

use App\Models\admin;
use Illuminate\Support\Facades\Http;

trait CalculateDistanceTrait
{
    /**
     * Haversine Formula 
     * Calculate distance in km between 2 places.
     *
     * @param string $lat1
     * @param string $lon1
     * @param string $lat2
     * @param string $lon2
     * @return float
     */

    function calculateDistanceByHaversineFormula($lat1, $lon1, $lat2, $lon2)
    {
        try {
            $earthRadius = 6371; // Radius of the earth in kilometers

            $latDifference = deg2rad($lat2 - $lat1);
            $lonDifference = deg2rad($lon2 - $lon1);

            $a = sin($latDifference / 2) * sin($latDifference / 2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($lonDifference / 2) * sin($lonDifference / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

            $distance = $earthRadius * $c; // Distance in kilometers

            return $distance;
        } catch (\Exception $e) {
            return 0;
        }
    }


    /**
     * Google Maps Distance Matrix Api
     * Calculate distance between 2 places in km.
     *
     * @param array $origin
     * @param array $destination
     * @return float
     */

    public function calculateDistance($origin, $destination)
    {
        try {
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';

            $response = Http::get($url, [
                'origins' => $origin['latitude'] . ',' . $origin['longitude'],
                'destinations' => $destination['latitude'] . ',' . $destination['longitude'],
                'key' => config('services.google.google_map_api_key'),
                'units' => 'metric', // Ensure metric units are used
                'mode' => 'driving', // Specify travel mode here ('driving', 'walking', 'bicycling', 'transit') default is driving
            ]);
            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['rows'][0]['elements'][0]['distance']['value'])) {
                    $distanceInMeters = $data['rows'][0]['elements'][0]['distance']['value'];
                    return $distanceInMeters / 1000; // Convert meters to kilometers
                }
            }
            $km = $this->calculateDistanceByHaversineFormula($origin['latitude'], $origin['longitude'], $destination['latitude'], $destination['longitude']);
            return $km;
        } catch (\Exception $e) {
            $km = $this->calculateDistanceByHaversineFormula($origin['latitude'], $origin['longitude'], $destination['latitude'], $destination['longitude']);
            return $km;
        }
    }


    /**
     * Google Maps Distance Matrix API
     * Calculate distance between multiple origins and single destination.
     *
     * @param array $origins
     * @param array $destination
     * @return array
     */

    public function calculateDistances($origins, $destination)
    {
        try {
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';
            $originChunks = collect($origins)->chunk(25);
            $results = [];

            foreach ($originChunks as $chunk) {

                $originString = collect($chunk)->map(function ($origin) {
                    return $origin['latitude'] . ',' . $origin['longitude'];
                })->implode('|');

                $response = Http::get($url, [
                    'origins' => $originString,
                    'destinations' => $destination['latitude'] . ',' . $destination['longitude'],
                    'key' => config('services.google.google_map_api_key'),
                    'units' => 'metric',
                    'mode' => 'driving'
                ]);
                if ($response->successful()) {
                    $data = $response->json();
                    if ($data['status'] === 'OK') {
                        foreach ($data['rows'] as $row) {
                            foreach ($row['elements'] as $element) {
                                if (isset($element['distance']) && isset($element['distance']['value'])) {
                                    $distanceValue = $element['distance']['value']; // This value is in meters
                                    // Check if distance is less than 1000 meters
                                    if ($distanceValue < 1000) {
                                        $element['distance']['text'] = $distanceValue . ' m';
                                    }
                                }
                                $results[] =  $element;
                            }
                        }
                    }
                }
            }
            if (empty($results)) {
                $results = $this->handleCalculateDistancesFailure($origins, $destination);
            }
            return $results;
        } catch (\Exception $e) {
            $results = $this->handleCalculateDistancesFailure($origins, $destination);
            return $results;
        }
    }

    /**
     * Handle failure of calculateDitances function using haversine formula
     * Calculate distance between multiple origins and single destination.
     *
     * @param array $origins
     * @param array $destination
     * @return array
     */

    public function handleCalculateDistancesFailure($origins, $destination)
    {
        $results = [];
        foreach ($origins as $origin) {
            $distance = $this->calculateDistanceByHaversineFormula($origin['latitude'], $origin['longitude'], $destination['latitude'], $destination['longitude']);
            $kmDistance = round($distance, 1);
            $mDistance = round($distance * 1000);

            $results[] = [
                'distance' => [
                    'value' => $mDistance,
                    'text' => $distance < 1 ? $mDistance . ' m' : $kmDistance . ' km'
                ]
            ];
        }
        return $results;
    }

    /**
     * Google Maps Distance Matrix API
     * Calculate distance between 2 places with duration.
     *
     * @param array $origin
     * @param array $destination
     * @return array
     */
    public function calculateDistanceWithDuration($origin, $destination)
    {
        try {
            $url = 'https://maps.googleapis.com/maps/api/distancematrix/json';

            $response = Http::get($url, [
                'origins' => $origin['latitude'] . ',' . $origin['longitude'],
                'destinations' => $destination['latitude'] . ',' . $destination['longitude'],
                'key' => config('services.google.google_map_api_key'),
                'units' => 'metric', // Ensure metric units are used
                'mode' => 'driving', // Specify travel mode here ('driving', 'walking', 'bicycling', 'transit') default is driving
            ]);
            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['rows'][0]['elements']) && isset($data['rows'][0]['elements'][0]['distance']['text'])) {
                    $element = $data['rows'][0]['elements'][0];
                    return $element;
                }
            }
            $element = $this->handleCalculateDistanceWithDurationFailure($origin, $destination);
            return $element;
        } catch (\Exception $e) {
            $element = $this->handleCalculateDistanceWithDurationFailure($origin, $destination);
            return $element;
        }
    }

    /**
     * Handle failure of calculateDistanceWithDuration using haversine formula
     * Calculate distance between 2 places with duration.
     *
     * @param array $origin
     * @param array $destination
     * @return array
     */
    public function handleCalculateDistanceWithDurationFailure($origin, $destination)
    {
        $distance = $this->calculateDistanceByHaversineFormula($origin['latitude'], $origin['longitude'], $destination['latitude'], $destination['longitude']);
        $distanceInMeters = round($distance * 1000);
        $distanceText = $distance < 1 ? $distanceInMeters . ' m' : round($distance, 1) . ' km';

        return [
            'distance' => [
                'value' => $distanceInMeters,
                'text' => $distanceText
            ],
            'duration' => [
                'value' => null,
                'text' => null
            ]
        ];
    }
}
