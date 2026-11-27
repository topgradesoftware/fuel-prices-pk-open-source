<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\FuelCompany;
use App\Models\FuelPrice;
use App\Models\FuelType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds PK / IN / BD countries, attaches existing PK cities & companies,
 * and adds India / Bangladesh metro catalogs with demo national prices.
 */
class CountryCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $pk = $this->seedCountry('PK', 'Pakistan', 'PKR', 'Rs', 10);
        $in = $this->seedCountry('IN', 'India', 'INR', '₹', 20);
        $bd = $this->seedCountry('BD', 'Bangladesh', 'BDT', '৳', 30);

        City::query()->whereNull('country_id')->update(['country_id' => $pk->id]);
        FuelCompany::query()->whereNull('country_id')->update(['country_id' => $pk->id]);
        FuelPrice::query()->whereNull('country_id')->update(['country_id' => $pk->id]);

        $this->seedIndia($in);
        $this->seedBangladesh($bd);
    }

    private function seedCountry(
        string $code,
        string $name,
        string $currency,
        string $symbol,
        int $sort,
    ): Country {
        return Country::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'currency' => $currency,
                'currency_symbol' => $symbol,
                'unit_volume' => 'L',
                'is_active' => true,
                'sort_order' => $sort,
            ],
        );
    }

    private function seedIndia(Country $country): void
    {
        $cities = [
            ['New Delhi', 'Delhi'],
            ['Mumbai', 'Maharashtra'],
            ['Bengaluru', 'Karnataka'],
            ['Hyderabad', 'Telangana'],
            ['Chennai', 'Tamil Nadu'],
            ['Kolkata', 'West Bengal'],
            ['Pune', 'Maharashtra'],
            ['Ahmedabad', 'Gujarat'],
            ['Jaipur', 'Rajasthan'],
            ['Lucknow', 'Uttar Pradesh'],
            ['Chandigarh', 'Chandigarh'],
            ['Kochi', 'Kerala'],
        ];
        $this->seedCities($country, $cities);

        $companies = [
            ['Indian Oil', 'IOC', 'indian-oil', 10, 'https://iocl.com'],
            ['Bharat Petroleum', 'BPCL', 'bpcl', 20, 'https://www.bharatpetroleum.in'],
            ['Hindustan Petroleum', 'HPCL', 'hpcl', 30, 'https://www.hindustanpetroleum.com'],
            ['National (India)', 'National', 'national-in', 190, null],
        ];
        $this->seedCompanies($country, $companies);

        $this->seedDemoNationalPrices($country, 'national-in', [
            'petrol' => 94.77,
            'hsd' => 87.67,
        ], '₹/L', 'INR');
    }

    private function seedBangladesh(Country $country): void
    {
        $cities = [
            ['Dhaka', 'Dhaka'],
            ['Chattogram', 'Chattogram'],
            ['Khulna', 'Khulna'],
            ['Rajshahi', 'Rajshahi'],
            ['Sylhet', 'Sylhet'],
            ['Barishal', 'Barishal'],
            ['Rangpur', 'Rangpur'],
            ['Gazipur', 'Dhaka'],
            ['Narayanganj', 'Dhaka'],
            ['Mymensingh', 'Mymensingh'],
        ];
        $this->seedCities($country, $cities);

        $companies = [
            ['Padma Oil', 'Padma', 'padma-oil', 10, null],
            ['Meghna Petroleum', 'Meghna', 'meghna', 20, null],
            ['National (Bangladesh)', 'National', 'national-bd', 190, null],
        ];
        $this->seedCompanies($country, $companies);

        $this->seedDemoNationalPrices($country, 'national-bd', [
            'petrol' => 125.00,
            'hsd' => 109.00,
        ], '৳/L', 'BDT');
    }

    /**
     * @param  list<array{0: string, 1: string}>  $cities
     */
    private function seedCities(Country $country, array $cities): void
    {
        foreach ($cities as [$name, $province]) {
            City::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'province' => $province,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: int, 4: ?string}>  $companies
     */
    private function seedCompanies(Country $country, array $companies): void
    {
        foreach ($companies as [$name, $short, $slug, $priority, $website]) {
            FuelCompany::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'country_id' => $country->id,
                    'name' => $name,
                    'short_name' => $short,
                    'website' => $website,
                    'priority' => $priority,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<string, float>  $prices
     */
    private function seedDemoNationalPrices(
        Country $country,
        string $companySlug,
        array $prices,
        string $unit,
        string $currency,
    ): void {
        $company = FuelCompany::query()->where('slug', $companySlug)->first();
        if (! $company) {
            return;
        }

        foreach ($prices as $fuelSlug => $amount) {
            $fuelType = FuelType::query()->where('slug', $fuelSlug)->first();
            if (! $fuelType) {
                continue;
            }

            FuelPrice::query()->updateOrCreate(
                [
                    'country_id' => $country->id,
                    'company_id' => $company->id,
                    'city_id' => null,
                    'fuel_type_id' => $fuelType->id,
                    'source' => 'seed',
                ],
                [
                    'price' => $amount,
                    'previous_price' => null,
                    'currency' => $currency,
                    'unit' => $unit,
                    'effective_date' => now()->toDateString(),
                    'source_url' => null,
                    'verified' => false,
                ],
            );
        }
    }
}
