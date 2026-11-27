<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->seedAdminUser();

        $this->call([
            FuelCatalogSeeder::class,
            CountryCatalogSeeder::class,
            // nnjeim/world → world_* tables (~250 countries, ~150k cities).
            WorldSeeder::class,
            // Copy world_* into fuel countries/cities.
            WorldFuelCatalogSeeder::class,
            FuelStationSeeder::class,
            ArticleSeeder::class, // includes SeoFuelGuidesSeeder
        ]);
    }

    private function seedAdminUser(): void
    {
        if (app()->environment('production')) {
            $email = env('ADMIN_EMAIL');
            $password = env('ADMIN_PASSWORD');

            if (! filled($email) || ! filled($password)) {
                $this->command?->warn(
                    'Skipping admin seed in production — set ADMIN_EMAIL and ADMIN_PASSWORD.',
                );

                return;
            }

            if (strlen((string) $password) < 12) {
                throw new \RuntimeException('ADMIN_PASSWORD must be at least 12 characters in production.');
            }

            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env('ADMIN_NAME', 'Fuel Prices Admin'),
                    'password' => Hash::make($password),
                    'auth_provider' => 'password',
                    'is_guest' => false,
                    'is_admin' => true,
                ],
            );

            return;
        }

        // Weak demo password only in local — staging/other envs must use ADMIN_* env vars.
        if (! app()->environment('local')) {
            $email = env('ADMIN_EMAIL');
            $password = env('ADMIN_PASSWORD');
            if (! filled($email) || ! filled($password)) {
                $this->command?->warn('Skipping admin seed — set ADMIN_EMAIL and ADMIN_PASSWORD.');

                return;
            }
            if (strlen((string) $password) < 12) {
                throw new \RuntimeException('ADMIN_PASSWORD must be at least 12 characters.');
            }
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => env('ADMIN_NAME', 'Fuel Prices Admin'),
                    'password' => Hash::make($password),
                    'auth_provider' => 'password',
                    'is_guest' => false,
                    'is_admin' => true,
                ],
            );

            return;
        }

        User::query()->updateOrCreate(
            ['email' => 'admin@oilprice.test'],
            [
                'name' => 'Oil Price Admin',
                'password' => Hash::make('password'),
                'auth_provider' => 'password',
                'is_guest' => false,
                'is_admin' => true,
            ],
        );
    }
}
