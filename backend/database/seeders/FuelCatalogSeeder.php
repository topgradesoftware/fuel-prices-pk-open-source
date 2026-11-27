<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\FuelCompany;
use App\Models\FuelType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FuelCatalogSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure PK exists before cities/companies (CountryCatalogSeeder also upserts).
        Country::query()->updateOrCreate(
            ['code' => 'PK'],
            [
                'name' => 'Pakistan',
                'currency' => 'PKR',
                'currency_symbol' => 'Rs',
                'unit_volume' => 'L',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );

        $this->seedCompanies();
        $this->seedCities();
        $this->seedFuelTypes();
    }

    private function pakistanId(): ?int
    {
        return Country::query()->where('code', 'PK')->value('id');
    }

    private function seedCompanies(): void
    {
        // Major OMCs / retail brands serving Pakistan (OCAC / market share).
        // TrackMate currently returns pso, shell, pakwheels — others are ready for prices.
        $companies = [
            ['name' => 'Pakistan State Oil', 'short_name' => 'PSO', 'slug' => 'pso', 'priority' => 10, 'website' => 'https://psopk.com'],
            ['name' => 'Gas & Oil Pakistan (GO)', 'short_name' => 'GO', 'slug' => 'go', 'priority' => 20, 'website' => 'https://gno.com.pk'],
            ['name' => 'PARCO / Total PARCO', 'short_name' => 'PARCO', 'slug' => 'total', 'priority' => 30, 'website' => 'https://www.parco.com.pk'],
            ['name' => 'Attock Petroleum (APL)', 'short_name' => 'APL', 'slug' => 'attock', 'priority' => 40, 'website' => 'https://www.apl.com.pk'],
            ['name' => 'Wafi Energy (Shell licensee)', 'short_name' => 'Wafi', 'slug' => 'wafi', 'priority' => 50, 'website' => null],
            ['name' => 'Shell Pakistan', 'short_name' => 'Shell', 'slug' => 'shell', 'priority' => 55, 'website' => 'https://www.shell.com.pk'],
            ['name' => 'Hascol Petroleum', 'short_name' => 'Hascol', 'slug' => 'hascol', 'priority' => 60, 'website' => 'https://www.hascol.com'],
            ['name' => 'Puma Energy Pakistan', 'short_name' => 'Puma', 'slug' => 'puma', 'priority' => 70, 'website' => null],
            ['name' => 'Cnergyico (Byco)', 'short_name' => 'Cnergyico', 'slug' => 'cnergyico', 'priority' => 80, 'website' => 'https://cnergyico.com'],
            ['name' => 'BE Energy (Caltex)', 'short_name' => 'BE', 'slug' => 'be_energy', 'priority' => 90, 'website' => null],
            ['name' => 'Euro Oil Pakistan', 'short_name' => 'Euro', 'slug' => 'euro_oil', 'priority' => 100, 'website' => null],
            ['name' => 'Taj Gasoline', 'short_name' => 'Taj', 'slug' => 'taj', 'priority' => 110, 'website' => null],
            ['name' => 'Askar Oil Services', 'short_name' => 'Askar', 'slug' => 'askar', 'priority' => 120, 'website' => null],
            ['name' => 'Zoom Petroleum', 'short_name' => 'Zoom', 'slug' => 'zoom', 'priority' => 130, 'website' => null],
            ['name' => 'Flow Petroleum', 'short_name' => 'Flow', 'slug' => 'flow', 'priority' => 140, 'website' => null],
            ['name' => 'Jinn Petroleum', 'short_name' => 'Jinn', 'slug' => 'jinn', 'priority' => 150, 'website' => null],
            ['name' => 'Vital Petroleum', 'short_name' => 'Vital', 'slug' => 'vital', 'priority' => 160, 'website' => null],
            ['name' => 'Quality-1 Petroleum', 'short_name' => 'Q1', 'slug' => 'quality1', 'priority' => 170, 'website' => null],
            ['name' => 'OTO Pakistan', 'short_name' => 'OTO', 'slug' => 'oto', 'priority' => 180, 'website' => null],
            ['name' => 'National / OGRA notified', 'short_name' => 'National', 'slug' => 'national', 'priority' => 190, 'website' => 'https://www.ogra.org.pk'],
            ['name' => 'PakWheels Aggregate', 'short_name' => 'PakWheels', 'slug' => 'pakwheels', 'priority' => 200, 'website' => 'https://www.pakwheels.com'],
        ];

        $pkId = $this->pakistanId();
        foreach ($companies as $company) {
            FuelCompany::query()->updateOrCreate(
                ['slug' => $company['slug']],
                $company + ['is_active' => true, 'country_id' => $pkId],
            );
        }
    }

    private function seedCities(): void
    {
        // District HQs + major towns used for location search / filters.
        // Fuel prices often fall back to national OGRA rates when city-specific
        // rows are missing — cities still appear in the picker.
        $cities = [
            // ICT / twin cities
            ['Islamabad', 'ICT'],
            ['Rawalpindi', 'Punjab'],
            // Punjab
            ['Lahore', 'Punjab'],
            ['Faisalabad', 'Punjab'],
            ['Multan', 'Punjab'],
            ['Gujranwala', 'Punjab'],
            ['Sialkot', 'Punjab'],
            ['Bahawalpur', 'Punjab'],
            ['Sargodha', 'Punjab'],
            ['Sheikhupura', 'Punjab'],
            ['Rahim Yar Khan', 'Punjab'],
            ['Jhang', 'Punjab'],
            ['Gujrat', 'Punjab'],
            ['Kasur', 'Punjab'],
            ['Sahiwal', 'Punjab'],
            ['Okara', 'Punjab'],
            ['Wah Cantonment', 'Punjab'],
            ['Dera Ghazi Khan', 'Punjab'],
            ['Muzaffargarh', 'Punjab'],
            ['Khanewal', 'Punjab'],
            ['Vehari', 'Punjab'],
            ['Pakpattan', 'Punjab'],
            ['Bahawalnagar', 'Punjab'],
            ['Chiniot', 'Punjab'],
            ['Toba Tek Singh', 'Punjab'],
            ['Jhelum', 'Punjab'],
            ['Mandi Bahauddin', 'Punjab'],
            ['Narowal', 'Punjab'],
            ['Hafizabad', 'Punjab'],
            ['Mianwali', 'Punjab'],
            ['Bhakkar', 'Punjab'],
            ['Khushab', 'Punjab'],
            ['Chakwal', 'Punjab'],
            ['Attock', 'Punjab'],
            ['Taxila', 'Punjab'],
            ['Kamoke', 'Punjab'],
            ['Wazirabad', 'Punjab'],
            ['Daska', 'Punjab'],
            ['Gujranwala Cantonment', 'Punjab'],
            ['Muridke', 'Punjab'],
            ['Kamalia', 'Punjab'],
            ['Gojra', 'Punjab'],
            ['Samundri', 'Punjab'],
            ['Jaranwala', 'Punjab'],
            ['Burewala', 'Punjab'],
            ['Hasilpur', 'Punjab'],
            ['Ahmadpur East', 'Punjab'],
            ['Lodhran', 'Punjab'],
            ['Khanpur', 'Punjab'],
            ['Sadiqabad', 'Punjab'],
            ['Liaquatpur', 'Punjab'],
            ['Kot Addu', 'Punjab'],
            ['Taunsa', 'Punjab'],
            ['Rajanpur', 'Punjab'],
            ['Layyah', 'Punjab'],
            ['Dunya Pur', 'Punjab'],
            ['Shorkot', 'Punjab'],
            ['Pir Mahal', 'Punjab'],
            ['Arifwala', 'Punjab'],
            ['Chichawatni', 'Punjab'],
            ['Renala Khurd', 'Punjab'],
            ['Pattoki', 'Punjab'],
            ['Bhai Pheru', 'Punjab'],
            ['Nankana Sahib', 'Punjab'],
            ['Sangla Hill', 'Punjab'],
            ['Shahkot', 'Punjab'],
            ['Phalia', 'Punjab'],
            ['Malakwal', 'Punjab'],
            ['Pind Dadan Khan', 'Punjab'],
            ['Gujar Khan', 'Punjab'],
            ['Kahuta', 'Punjab'],
            ['Murree', 'Punjab'],
            ['Kotli Sattian', 'Punjab'],
            ['Talagang', 'Punjab'],
            ['Pindi Gheb', 'Punjab'],
            ['Hazro', 'Punjab'],
            ['Hasan Abdal', 'Punjab'],
            ['Jampur', 'Punjab'],
            ['Alipur', 'Punjab'],
            ['Jalalpur Pirwala', 'Punjab'],
            ['Kabirwala', 'Punjab'],
            ['Mian Channu', 'Punjab'],
            ['Mailsi', 'Punjab'],
            ['Fort Abbas', 'Punjab'],
            ['Haroonabad', 'Punjab'],
            ['Chishtian', 'Punjab'],
            ['Minchinabad', 'Punjab'],
            // Sindh
            ['Karachi', 'Sindh'],
            ['Hyderabad', 'Sindh'],
            ['Sukkur', 'Sindh'],
            ['Larkana', 'Sindh'],
            ['Nawabshah', 'Sindh'],
            ['Mirpur Khas', 'Sindh'],
            ['Jacobabad', 'Sindh'],
            ['Shikarpur', 'Sindh'],
            ['Khairpur', 'Sindh'],
            ['Dadu', 'Sindh'],
            ['Thatta', 'Sindh'],
            ['Badin', 'Sindh'],
            ['Tando Adam', 'Sindh'],
            ['Tando Allahyar', 'Sindh'],
            ['Tando Muhammad Khan', 'Sindh'],
            ['Umerkot', 'Sindh'],
            ['Sanghar', 'Sindh'],
            ['Naushahro Feroze', 'Sindh'],
            ['Shahdadkot', 'Sindh'],
            ['Kandhkot', 'Sindh'],
            ['Ghotki', 'Sindh'],
            ['Rohri', 'Sindh'],
            ['Kotri', 'Sindh'],
            ['Jamshoro', 'Sindh'],
            ['Matiari', 'Sindh'],
            ['Hala', 'Sindh'],
            ['Sehwan', 'Sindh'],
            ['Mehar', 'Sindh'],
            ['Kambar', 'Sindh'],
            ['Kashmore', 'Sindh'],
            ['Mithi', 'Sindh'],
            ['Chachro', 'Sindh'],
            ['Kunri', 'Sindh'],
            ['Pano Aqil', 'Sindh'],
            ['Gambat', 'Sindh'],
            ['Pir Jo Goth', 'Sindh'],
            ['Matli', 'Sindh'],
            ['Tando Jam', 'Sindh'],
            ['Mirpur Mathelo', 'Sindh'],
            ['Daharki', 'Sindh'],
            // KPK
            ['Peshawar', 'KPK'],
            ['Mardan', 'KPK'],
            ['Mingora', 'KPK'],
            ['Swat', 'KPK'],
            ['Abbottabad', 'KPK'],
            ['Kohat', 'KPK'],
            ['Dera Ismail Khan', 'KPK'],
            ['Bannu', 'KPK'],
            ['Charsadda', 'KPK'],
            ['Nowshera', 'KPK'],
            ['Swabi', 'KPK'],
            ['Mansehra', 'KPK'],
            ['Haripur', 'KPK'],
            ['Battagram', 'KPK'],
            ['Shangla', 'KPK'],
            ['Buner', 'KPK'],
            ['Lower Dir', 'KPK'],
            ['Upper Dir', 'KPK'],
            ['Chitral', 'KPK'],
            ['Timergara', 'KPK'],
            ['Hangu', 'KPK'],
            ['Karak', 'KPK'],
            ['Lakki Marwat', 'KPK'],
            ['Tank', 'KPK'],
            ['Kohistan', 'KPK'],
            ['Torghar', 'KPK'],
            ['Malakand', 'KPK'],
            ['Bajaur', 'KPK'],
            ['Mohmand', 'KPK'],
            ['Khyber', 'KPK'],
            ['Orakzai', 'KPK'],
            ['Kurram', 'KPK'],
            ['North Waziristan', 'KPK'],
            ['South Waziristan', 'KPK'],
            ['Jamrud', 'KPK'],
            ['Landi Kotal', 'KPK'],
            ['Parachinar', 'KPK'],
            ['Wana', 'KPK'],
            ['Miranshah', 'KPK'],
            ['Topi', 'KPK'],
            ['Tangi', 'KPK'],
            ['Takht Bhai', 'KPK'],
            ['Kabal', 'KPK'],
            ['Batkhela', 'KPK'],
            ['Chakdara', 'KPK'],
            ['Besham', 'KPK'],
            ['Dassu', 'KPK'],
            ['Balakot', 'KPK'],
            ['Oghi', 'KPK'],
            ['Havelian', 'KPK'],
            // Balochistan
            ['Quetta', 'Balochistan'],
            ['Gwadar', 'Balochistan'],
            ['Turbat', 'Balochistan'],
            ['Khuzdar', 'Balochistan'],
            ['Hub', 'Balochistan'],
            ['Chaman', 'Balochistan'],
            ['Zhob', 'Balochistan'],
            ['Sibi', 'Balochistan'],
            ['Loralai', 'Balochistan'],
            ['Dera Murad Jamali', 'Balochistan'],
            ['Usta Muhammad', 'Balochistan'],
            ['Nushki', 'Balochistan'],
            ['Kalat', 'Balochistan'],
            ['Mastung', 'Balochistan'],
            ['Pishin', 'Balochistan'],
            ['Qila Abdullah', 'Balochistan'],
            ['Qila Saifullah', 'Balochistan'],
            ['Musakhel', 'Balochistan'],
            ['Barkhan', 'Balochistan'],
            ['Kohlu', 'Balochistan'],
            ['Dera Bugti', 'Balochistan'],
            ['Jaffarabad', 'Balochistan'],
            ['Jhal Magsi', 'Balochistan'],
            ['Kachhi', 'Balochistan'],
            ['Nasirabad', 'Balochistan'],
            ['Kharan', 'Balochistan'],
            ['Washuk', 'Balochistan'],
            ['Panjgur', 'Balochistan'],
            ['Awaran', 'Balochistan'],
            ['Lasbela', 'Balochistan'],
            ['Uthal', 'Balochistan'],
            ['Pasni', 'Balochistan'],
            ['Ormara', 'Balochistan'],
            ['Dalbandin', 'Balochistan'],
            ['Taftan', 'Balochistan'],
            // Gilgit-Baltistan
            ['Gilgit', 'GB'],
            ['Skardu', 'GB'],
            ['Hunza', 'GB'],
            ['Aliabad', 'GB'],
            ['Karimabad', 'GB'],
            ['Ghanche', 'GB'],
            ['Khaplu', 'GB'],
            ['Astore', 'GB'],
            ['Diamer', 'GB'],
            ['Chilas', 'GB'],
            ['Ghizer', 'GB'],
            ['Gupis', 'GB'],
            ['Nagar', 'GB'],
            ['Shigar', 'GB'],
            ['Roundu', 'GB'],
            // Azad Jammu & Kashmir
            ['Muzaffarabad', 'AJK'],
            ['Mirpur', 'AJK'],
            ['Kotli', 'AJK'],
            ['Rawalakot', 'AJK'],
            ['Bhimber', 'AJK'],
            ['Bagh', 'AJK'],
            ['Pallandri', 'AJK'],
            ['Hattian Bala', 'AJK'],
            ['Neelum', 'AJK'],
            ['Athmuqam', 'AJK'],
            ['Haveli', 'AJK'],
            ['Forward Kahuta', 'AJK'],
        ];

        $pkId = $this->pakistanId();
        foreach ($cities as [$name, $province]) {
            $this->upsertPkCity(Str::slug($name), $name, $province, $pkId);
        }

        // Explicit slugs for common aliases / TrackMate spellings
        $aliases = [
            ['d-i-khan', 'Dera Ismail Khan', 'KPK'],
            ['di-khan', 'Dera Ismail Khan', 'KPK'],
            ['dera-ismail-khan', 'Dera Ismail Khan', 'KPK'],
            ['gawadar', 'Gwadar', 'Balochistan'],
            ['nawabsah', 'Nawabshah', 'Sindh'],
            ['shaheed-benazirabad', 'Nawabshah', 'Sindh'],
            ['wah', 'Wah Cantonment', 'Punjab'],
            ['wah-cantt', 'Wah Cantonment', 'Punjab'],
        ];
        foreach ($aliases as [$slug, $name, $province]) {
            $this->upsertPkCity($slug, $name, $province, $pkId);
        }
    }

    private function upsertPkCity(string $slug, string $name, string $province, ?int $pkId): void
    {
        $city = City::query()
            ->where('slug', $slug)
            ->where(function ($q) use ($pkId) {
                $q->where('country_id', $pkId)->orWhereNull('country_id');
            })
            ->first();

        if ($city) {
            $city->update([
                'name' => $name,
                'province' => $province,
                'is_active' => true,
                'country_id' => $pkId,
            ]);

            return;
        }

        City::query()->create([
            'country_id' => $pkId,
            'slug' => $slug,
            'name' => $name,
            'province' => $province,
            'is_active' => true,
        ]);
    }

    private function seedFuelTypes(): void
    {
        $types = [
            // Retail motor fuels
            ['Petrol (MS / PMG)', 'petrol', 'petrol', 'Rs/L', 10],
            ['HOBC (High Octane)', 'hobc', 'petrol', 'Rs/L', 15],
            ['Hi-Octane', 'hi_octane', 'petrol', 'Rs/L', 20],
            ['Octane+', 'octane_plus', 'petrol', 'Rs/L', 25],
            ['Premium Petrol', 'premium', 'petrol', 'Rs/L', 30],
            ['Super Petrol', 'super', 'petrol', 'Rs/L', 35],
            ['E-10 Petrol', 'e10', 'petrol', 'Rs/L', 40],
            // Diesel & oils
            ['HSD Diesel', 'hsd', 'diesel', 'Rs/L', 50],
            ['Light Diesel Oil (LDO)', 'ldo', 'diesel', 'Rs/L', 55],
            ['LSD', 'lsd', 'diesel', 'Rs/L', 58],
            // Gas
            ['LPG', 'lpg', 'gas', 'Rs/kg', 70],
            ['CNG', 'cng', 'gas', 'Rs/kg', 75],
            // Other petroleum
            ['Kerosene (SKO)', 'kerosene', 'other', 'Rs/L', 80],
            ['Furnace Oil', 'furnace_oil', 'industrial', 'Rs/L', 90],
            ['JP-1 Jet Fuel', 'jp1', 'industrial', 'Rs/L', 100],
        ];

        foreach ($types as [$name, $slug, $category, $unit, $priority]) {
            FuelType::query()->updateOrCreate(
                ['slug' => $slug],
                compact('name', 'category', 'unit', 'priority') + ['is_active' => true],
            );
        }
    }
}
