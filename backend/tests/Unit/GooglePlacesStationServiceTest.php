<?php

namespace Tests\Unit;

use App\Services\Maps\GooglePlacesStationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GooglePlacesStationServiceTest extends TestCase
{
    public function test_nearby_maps_places_new_response(): void
    {
        Http::fake([
            'places.googleapis.com/*' => Http::response([
                'places' => [
                    [
                        'id' => 'places/abc',
                        'displayName' => ['text' => 'PSO Petrol Pump'],
                        'formattedAddress' => 'Lahore',
                        'location' => ['latitude' => 31.5204, 'longitude' => 74.3587],
                        'googleMapsUri' => 'https://maps.google.com/?cid=1',
                        'currentOpeningHours' => ['openNow' => true],
                    ],
                ],
            ], 200),
        ]);

        $service = new GooglePlacesStationService('test-key');
        $rows = $service->nearbyGasStations(31.52, 74.35, 5000, 10);

        $this->assertCount(1, $rows);
        $this->assertSame('PSO Petrol Pump', $rows[0]['name']);
        $this->assertSame('pso', $rows[0]['company_id']);
        $this->assertTrue($rows[0]['is_open_now']);
        $this->assertSame('google_places', $rows[0]['source']);
        $this->assertGreaterThan(0, $rows[0]['distance_m']);
    }

    public function test_disabled_without_api_key(): void
    {
        $service = new GooglePlacesStationService(null);
        $this->assertFalse($service->enabled());
        $this->assertSame([], $service->nearbyGasStations(31.52, 74.35));
    }
}
