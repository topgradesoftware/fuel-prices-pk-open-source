<?php

namespace Tests\Feature\Api\V1;

use App\Models\City;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MvpApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_guest_auth_returns_sanctum_token(): void
    {
        $this->postJson('/api/v1/auth/guest', [
            'name' => 'MVP Tester',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'is_guest'],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'MVP Tester',
            'is_guest' => true,
        ]);
    }

    public function test_prices_endpoint_returns_envelope_with_items(): void
    {
        $company = FuelCompany::query()->create([
            'name' => 'Pakistan State Oil',
            'short_name' => 'PSO',
            'slug' => 'pso',
            'is_active' => true,
            'priority' => 10,
        ]);

        $type = FuelType::query()->create([
            'name' => 'Petrol (MS)',
            'slug' => 'petrol',
            'category' => 'petrol',
            'unit' => 'Rs/L',
            'is_active' => true,
            'priority' => 10,
        ]);

        City::query()->create([
            'name' => 'Lahore',
            'slug' => 'lahore',
            'province' => 'Punjab',
            'is_active' => true,
        ]);

        FuelPrice::query()->create([
            'company_id' => $company->id,
            'city_id' => null,
            'fuel_type_id' => $type->id,
            'price' => 316.15,
            'previous_price' => 314.00,
            'currency' => 'PKR',
            'unit' => 'Rs/L',
            'effective_date' => now()->toDateString(),
            'source' => 'manual',
            'verified' => true,
        ]);

        $this->getJson('/api/v1/prices')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        [
                            'company_id',
                            'fuel_type_id',
                            'price',
                        ],
                    ],
                ],
            ]);
    }

    public function test_authenticated_me_requires_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        $user = User::factory()->create([
            'is_guest' => true,
            'auth_provider' => 'guest',
        ]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
