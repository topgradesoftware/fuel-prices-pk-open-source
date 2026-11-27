<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiInputValidationTest extends TestCase
{
    public function test_prices_rejects_invalid_slug_and_unknown_fields(): void
    {
        $this->getJson('/api/v1/prices?city=NOT VALID')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');

        $this->getJson('/api/v1/prices?city=karachi&evil=1')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_prices_valid_query_is_not_rejected_by_schema(): void
    {
        // Without a migrated DB the handler may 500 after validation; schema must still pass.
        $status = $this->getJson('/api/v1/prices?city=karachi&country=pk')->status();
        $this->assertNotSame(422, $status);
    }

    public function test_history_rejects_out_of_range_days(): void
    {
        $this->getJson('/api/v1/history?days=999')
            ->assertStatus(422);
    }

    public function test_nearby_rejects_invalid_coordinates(): void
    {
        $this->getJson('/api/v1/stations/nearby?lat=999&lng=0')
            ->assertStatus(422);
    }

    public function test_notifications_rejects_oversized_per_page(): void
    {
        $this->getJson('/api/v1/notifications?per_page=500')
            ->assertStatus(422);
    }

    public function test_google_auth_rejects_short_token_and_unknown_fields(): void
    {
        $this->postJson('/api/v1/auth/google', ['id_token' => 'short'])
            ->assertStatus(422);

        $this->postJson('/api/v1/auth/google', [
            'id_token' => str_repeat('a', 40),
            'extra' => 'nope',
        ])->assertStatus(422);
    }

    public function test_disabled_auth_stubs_reject_any_body(): void
    {
        $this->postJson('/api/v1/auth/apple', ['foo' => 'bar'])
            ->assertStatus(422);
    }

    public function test_push_subscribe_requires_nested_keys(): void
    {
        $this->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://example.com/push',
        ])->assertStatus(422);
    }
}
