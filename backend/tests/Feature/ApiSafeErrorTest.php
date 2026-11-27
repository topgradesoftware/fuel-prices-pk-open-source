<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiSafeErrorTest extends TestCase
{
    public function test_api_server_errors_never_leak_exception_details_even_when_debug_is_on(): void
    {
        config(['app.debug' => true]);

        Route::get('/api/v1/__test_safe_error', function () {
            throw new RuntimeException('SQLSTATE[HY000] /var/www/backend/secret.php stack trace');
        });

        $response = $this->getJson('/api/v1/__test_safe_error');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Server error.',
            ]);

        $body = $response->getContent();
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('/var/www', $body);
        $this->assertStringNotContainsString('stack trace', $body);
        $this->assertStringNotContainsString('secret.php', $body);
    }

    public function test_api_validation_errors_stay_generic_with_field_details_only(): void
    {
        $response = $this->postJson('/api/v1/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.');
    }
}
