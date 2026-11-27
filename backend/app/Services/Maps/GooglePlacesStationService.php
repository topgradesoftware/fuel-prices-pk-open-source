<?php

namespace App\Services\Maps;

use App\Support\SafeError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class GooglePlacesStationService
{
    public function __construct(
        private readonly ?string $apiKey = null,
    ) {}

    public function enabled(): bool
    {
        return filled($this->apiKey);
    }

    /**
     * @return list<array{
     *     id: string,
     *     name: string,
     *     address: string|null,
     *     lat: float,
     *     lng: float,
     *     distance_m: float,
     *     distance_km: float,
     *     is_open_24h: bool,
     *     is_open_now: bool|null,
     *     company_id: string|null,
     *     company: string|null,
     *     city_id: null,
     *     city: null,
     *     maps_url: string,
     *     source: string
     * }>
     */
    public function nearbyGasStations(float $lat, float $lng, int $radiusMeters = 5000, int $limit = 20): array
    {
        if (! $this->enabled()) {
            return [];
        }

        $radiusMeters = max(500, min($radiusMeters, 20000));
        $limit = max(1, min($limit, 20));

        try {
            return $this->searchNearbyNew($lat, $lng, $radiusMeters, $limit);
        } catch (\Throwable $e) {
            SafeError::log($e, 'Google Places (new) nearby search failed', level: 'warning');
            try {
                return $this->searchNearbyLegacy($lat, $lng, $radiusMeters, $limit);
            } catch (\Throwable $legacy) {
                SafeError::log($legacy, 'Google Places (legacy) nearby search failed');
                throw new RuntimeException('Nearby stations unavailable.');
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchNearbyNew(float $lat, float $lng, int $radiusMeters, int $limit): array
    {
        $response = Http::timeout(20)
            ->withHeaders([
                'X-Goog-Api-Key' => (string) $this->apiKey,
                'X-Goog-FieldMask' => implode(',', [
                    'places.id',
                    'places.displayName',
                    'places.formattedAddress',
                    'places.location',
                    'places.googleMapsUri',
                    'places.currentOpeningHours',
                    'places.regularOpeningHours',
                ]),
            ])
            ->post('https://places.googleapis.com/v1/places:searchNearby', [
                'includedTypes' => ['gas_station'],
                'maxResultCount' => $limit,
                'rankPreference' => 'DISTANCE',
                'locationRestriction' => [
                    'circle' => [
                        'center' => [
                            'latitude' => $lat,
                            'longitude' => $lng,
                        ],
                        'radius' => (float) $radiusMeters,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Places API (new) HTTP '.$response->status());
        }

        $places = $response->json('places') ?? [];
        if (! is_array($places)) {
            return [];
        }

        $out = [];
        foreach ($places as $place) {
            if (! is_array($place)) {
                continue;
            }
            $mapped = $this->mapNewPlace($place, $lat, $lng);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        usort($out, fn ($a, $b) => $a['distance_m'] <=> $b['distance_m']);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchNearbyLegacy(float $lat, float $lng, int $radiusMeters, int $limit): array
    {
        $response = Http::timeout(20)
            ->acceptJson()
            ->get('https://maps.googleapis.com/maps/api/place/nearbysearch/json', [
                'location' => $lat.','.$lng,
                'radius' => $radiusMeters,
                'type' => 'gas_station',
                'key' => $this->apiKey,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Places API (legacy) HTTP '.$response->status());
        }

        $body = $response->json() ?? [];
        $status = (string) ($body['status'] ?? '');
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new RuntimeException('Places API (legacy) status '.$status);
        }

        $results = $body['results'] ?? [];
        if (! is_array($results)) {
            return [];
        }

        $out = [];
        foreach (array_slice($results, 0, $limit) as $place) {
            if (! is_array($place)) {
                continue;
            }
            $mapped = $this->mapLegacyPlace($place, $lat, $lng);
            if ($mapped !== null) {
                $out[] = $mapped;
            }
        }

        usort($out, fn ($a, $b) => $a['distance_m'] <=> $b['distance_m']);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>|null
     */
    private function mapNewPlace(array $place, float $originLat, float $originLng): ?array
    {
        $location = $place['location'] ?? null;
        if (! is_array($location)) {
            return null;
        }

        $plat = (float) ($location['latitude'] ?? 0);
        $plng = (float) ($location['longitude'] ?? 0);
        if ($plat === 0.0 && $plng === 0.0) {
            return null;
        }

        $name = (string) data_get($place, 'displayName.text', 'Petrol pump');
        $distance = $this->haversineMeters($originLat, $originLng, $plat, $plng);
        $company = $this->inferCompany($name);
        $openNow = data_get($place, 'currentOpeningHours.openNow');
        $placeId = (string) ($place['id'] ?? Str::slug($name.'-'.$plat.'-'.$plng));

        return [
            'id' => 'gplace:'.$placeId,
            'name' => $name,
            'address' => isset($place['formattedAddress']) ? (string) $place['formattedAddress'] : null,
            'lat' => $plat,
            'lng' => $plng,
            'distance_m' => round($distance, 1),
            'distance_km' => round($distance / 1000, 2),
            'is_open_24h' => false,
            'is_open_now' => is_bool($openNow) ? $openNow : null,
            'company_id' => $company['id'],
            'company' => $company['label'],
            'city_id' => null,
            'city' => null,
            'maps_url' => (string) ($place['googleMapsUri'] ?? $this->directionsUrl($plat, $plng)),
            'source' => 'google_places',
        ];
    }

    /**
     * @param  array<string, mixed>  $place
     * @return array<string, mixed>|null
     */
    private function mapLegacyPlace(array $place, float $originLat, float $originLng): ?array
    {
        $plat = (float) data_get($place, 'geometry.location.lat', 0);
        $plng = (float) data_get($place, 'geometry.location.lng', 0);
        if ($plat === 0.0 && $plng === 0.0) {
            return null;
        }

        $name = (string) ($place['name'] ?? 'Petrol pump');
        $distance = $this->haversineMeters($originLat, $originLng, $plat, $plng);
        $company = $this->inferCompany($name);
        $placeId = (string) ($place['place_id'] ?? Str::slug($name.'-'.$plat.'-'.$plng));
        $openNow = data_get($place, 'opening_hours.open_now');

        return [
            'id' => 'gplace:'.$placeId,
            'name' => $name,
            'address' => isset($place['vicinity']) ? (string) $place['vicinity'] : null,
            'lat' => $plat,
            'lng' => $plng,
            'distance_m' => round($distance, 1),
            'distance_km' => round($distance / 1000, 2),
            'is_open_24h' => false,
            'is_open_now' => is_bool($openNow) ? $openNow : null,
            'company_id' => $company['id'],
            'company' => $company['label'],
            'city_id' => null,
            'city' => null,
            'maps_url' => $this->directionsUrl($plat, $plng),
            'source' => 'google_places',
        ];
    }

    /**
     * @return array{id: string|null, label: string|null}
     */
    private function inferCompany(string $name): array
    {
        $n = Str::lower($name);

        return match (true) {
            str_contains($n, 'pso') || str_contains($n, 'pakistan state oil') => ['id' => 'pso', 'label' => 'PSO'],
            str_contains($n, 'shell') || str_contains($n, 'wafi') => ['id' => 'shell', 'label' => 'Shell'],
            str_contains($n, 'total') || str_contains($n, 'parco') => ['id' => 'parco', 'label' => 'PARCO'],
            str_contains($n, 'attock') || str_contains($n, 'apl') => ['id' => 'apl', 'label' => 'APL'],
            str_contains($n, 'hascol') => ['id' => 'hascol', 'label' => 'Hascol'],
            str_contains($n, 'go station') || preg_match('/\bgo\b/', $n) === 1 => ['id' => 'go', 'label' => 'GO'],
            str_contains($n, 'puma') => ['id' => 'puma', 'label' => 'Puma'],
            str_contains($n, 'caltex') || str_contains($n, 'be energy') => ['id' => 'be', 'label' => 'BE'],
            default => ['id' => null, 'label' => null],
        };
    }

    private function directionsUrl(float $lat, float $lng): string
    {
        return 'https://www.google.com/maps/dir/?api=1&destination='.$lat.','.$lng;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }
}
