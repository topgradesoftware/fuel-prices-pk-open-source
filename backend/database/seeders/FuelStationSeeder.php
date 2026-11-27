<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\FuelCompany;
use App\Models\FuelStation;
use Illuminate\Database\Seeder;

class FuelStationSeeder extends Seeder
{
    public function run(): void
    {
        $company = fn (string $slug) => FuelCompany::query()->where('slug', $slug)->first();
        $city = fn (string $slug) => City::query()->where('slug', $slug)->first();

        $rows = [
            // Lahore
            ['pso', 'lahore', 'PSO Jail Road', 'Jail Road, Lahore', 31.5204, 74.3587, true],
            ['pso', 'lahore', 'PSO Gulberg', 'Main Boulevard Gulberg, Lahore', 31.5102, 74.3441, false],
            ['shell', 'lahore', 'Shell MM Alam', 'MM Alam Road, Lahore', 31.5169, 74.3510, true],
            ['total', 'lahore', 'Total PARCO DHA', 'Y-Block DHA, Lahore', 31.4697, 74.4100, false],
            ['go', 'lahore', 'GO Thokar', 'Thokar Niaz Baig, Lahore', 31.4622, 74.2415, true],
            ['attock', 'lahore', 'APL Canal Road', 'Canal Road, Lahore', 31.4810, 74.3030, false],
            ['hascol', 'lahore', 'Hascol Ferozepur', 'Ferozepur Road, Lahore', 31.4690, 74.3210, true],
            // Karachi
            ['pso', 'karachi', 'PSO Clifton', 'Clifton Block 2, Karachi', 24.8138, 67.0225, true],
            ['shell', 'karachi', 'Shell Shahrah-e-Faisal', 'Shahrah-e-Faisal, Karachi', 24.8738, 67.0920, true],
            ['go', 'karachi', 'GO Defence', 'DHA Phase 6, Karachi', 24.8020, 67.0650, true],
            ['attock', 'karachi', 'APL Korangi', 'Korangi Industrial Area, Karachi', 24.8450, 67.1200, false],
            ['puma', 'karachi', 'Puma University Road', 'University Road, Karachi', 24.9200, 67.0900, true],
            ['cnergyico', 'karachi', 'Cnergyico Hub Link', 'Hub River Road, Karachi', 24.9000, 66.9800, false],
            // Islamabad / Rawalpindi
            ['pso', 'islamabad', 'PSO Blue Area', 'Jinnah Avenue, Islamabad', 33.7077, 73.0501, true],
            ['shell', 'islamabad', 'Shell F-10', 'F-10 Markaz, Islamabad', 33.6938, 73.0120, false],
            ['go', 'islamabad', 'GO G-9', 'G-9 Markaz, Islamabad', 33.6900, 73.0365, true],
            ['attock', 'rawalpindi', 'APL Murree Road', 'Murree Road, Rawalpindi', 33.6050, 73.0700, true],
            ['total', 'islamabad', 'PARCO I-8', 'I-8 Markaz, Islamabad', 33.6680, 73.0750, false],
            // Other cities
            ['pso', 'faisalabad', 'PSO Satiana Road', 'Satiana Road, Faisalabad', 31.4180, 73.0790, true],
            ['go', 'multan', 'GO Bosan Road', 'Bosan Road, Multan', 30.1980, 71.4680, true],
            ['pso', 'peshawar', 'PSO University Road', 'University Road, Peshawar', 34.0150, 71.5250, true],
            ['shell', 'quetta', 'Shell Jinnah Road', 'Jinnah Road, Quetta', 30.1950, 67.0100, false],
            ['pso', 'hyderabad', 'PSO Latifabad', 'Latifabad, Hyderabad', 25.3670, 68.3470, true],
            ['be_energy', 'lahore', 'BE Energy Raiwind', 'Raiwind Road, Lahore', 31.4000, 74.2500, false],
        ];

        foreach ($rows as [$companySlug, $citySlug, $name, $address, $lat, $lng, $open24]) {
            $co = $company($companySlug);
            $ci = $city($citySlug);

            if (! $co || ! $ci) {
                continue;
            }

            FuelStation::query()->updateOrCreate(
                [
                    'company_id' => $co->id,
                    'name' => $name,
                ],
                [
                    'city_id' => $ci->id,
                    'address' => $address,
                    'lat' => $lat,
                    'lng' => $lng,
                    'is_open_24h' => $open24,
                    'is_active' => true,
                ],
            );
        }
    }
}
