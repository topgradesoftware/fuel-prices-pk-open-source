<?php

namespace App\Services\Importers;

use App\Contracts\ImporterInterface;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;
use App\Models\FuelPriceHistory;
use Carbon\Carbon;
use Exception;

class OgraImporter implements ImporterInterface
{
    protected string $sourceUrl = 'https://www.ogra.org.pk/petroleum-prices'; // Placeholder

    public function download(): mixed
    {
        $response = Http::timeout(30)->get($this->sourceUrl);

        if (!$response->successful()) {
            throw new Exception("Failed to fetch OGRA data. Status: " . $response->status());
        }

        return $response->body();
    }

    public function parse(mixed $rawData): array
    {
        $crawler = new Crawler($rawData);
        $parsedData = [];

        // Assume there is a table with rows containing: [Date, Petrol Price, Diesel Price, Kerosene, LDO]
        // This selector (.historical-prices-table tr) will need to be adjusted once the real OGRA HTML structure is known.
        $crawler->filter('table.historical-prices-table tbody tr')->each(function (Crawler $node) use (&$parsedData) {
            $tds = $node->filter('td');
            
            if ($tds->count() >= 5) {
                try {
                    $dateText = trim($tds->eq(0)->text());
                    $petrol = (float) str_replace(',', '', trim($tds->eq(1)->text()));
                    $diesel = (float) str_replace(',', '', trim($tds->eq(2)->text()));

                    $parsedData[] = [
                        'effective_date' => Carbon::parse($dateText)->format('Y-m-d'),
                        'petrol_price' => $petrol,
                        'diesel_price' => $diesel,
                        'source_url' => $this->sourceUrl,
                        'notification_ref' => 'Extracted via Scraper',
                    ];
                } catch (\Throwable $e) {
                    // Skip unparseable rows
                }
            }
        });

        return $parsedData;
    }

    public function validate(array $parsedData): bool
    {
        foreach ($parsedData as $row) {
            if (empty($row['effective_date']) || $row['petrol_price'] <= 0 || $row['diesel_price'] <= 0) {
                return false;
            }
        }
        return true;
    }

    public function store(array $validatedData): void
    {
        foreach ($validatedData as $row) {
            // Note: FuelPriceHistory usually relates to a fuel_price_id (FuelType).
            // For now, this is an example of inserting the extracted historical data.
            FuelPriceHistory::updateOrCreate(
                [
                    'effective_at' => $row['effective_date'],
                    'source_url' => $row['source_url'],
                ],
                [
                    'new_price' => $row['petrol_price'], // Assuming this is saving petrol for example
                    'source' => 'OGRA Official Website',
                    'notification_ref' => $row['notification_ref'],
                ]
            );
        }
    }
}
