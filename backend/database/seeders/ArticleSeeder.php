<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

class ArticleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->articles() as $row) {
            Article::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['status' => Article::STATUS_PUBLISHED],
            );
        }

        // High-intent Pakistan fuel SEO guides (petrol/diesel/LPG/cities/OGRA).
        $this->call(SeoFuelGuidesSeeder::class);

        // Long-form evergreen explainers (AdSense content depth).
        $this->call(AdSenseLongFormGuidesSeeder::class);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        $now = now()->subDays(2);

        return [
            [
                'slug' => 'why-petrol-prices-change-in-pakistan',
                'title' => 'Why Petrol Prices Change in Pakistan',
                'excerpt' => 'A plain-language look at OGRA pricing, international crude, the rupee, and the petrol levy — and why rates can move often.',
                'category' => 'petrol-prices',
                'tags' => ['petrol', 'OGRA', 'pricing'],
                'is_featured' => true,
                'is_trending' => true,
                'reading_minutes' => 6,
                'published_at' => $now->copy()->subDays(1),
                'faq' => [
                    [
                        'question' => 'Who sets petrol prices in Pakistan?',
                        'answer' => 'Retail petrol and diesel prices are shaped by OGRA’s pricing mechanism, international product prices, exchange rates, and federal taxes/levy — then OMCs sell at notified rates.',
                    ],
                    [
                        'question' => 'Why do prices change so often?',
                        'answer' => 'Pakistan has moved toward more frequent revisions. Crude moves, FX, and tax/levy decisions can all shift the pump price quickly.',
                    ],
                ],
                'body' => <<<'HTML'
<p>Petrol prices in Pakistan feel unpredictable because several moving parts land in one pump figure. Understanding those parts helps you plan trips and budgets without guessing.</p>
<h2>International product prices</h2>
<p>Pakistan imports refined products and is exposed to global petrol and diesel benchmarks. When international prices rise, the cost of bringing fuel into the country rises too — even before local taxes.</p>
<h2>Exchange rate</h2>
<p>Fuel is priced in dollars on the world market. A weaker rupee increases the rupee cost of the same cargo, which feeds into the domestic ex-refinery / import parity calculation.</p>
<h2>OGRA pricing mechanism</h2>
<p>OGRA publishes the framework that translates international prices, freight, margins and taxes into notified retail rates. OMCs then sell against those notified figures (city/OMC differences can still appear in practice).</p>
<h2>Petrol levy and taxes</h2>
<p>Federal petrol levy and sales tax are a large share of the retail price. Levy changes can move the pump price even when crude is flat. See our separate guide on the petrol levy for a deeper walkthrough.</p>
<h2>What you can do</h2>
<p>Check <a href="/petrol-price-today">today’s petrol price</a>, compare cities, and use the <a href="/fuel-cost-calculator">fuel cost calculator</a> after each revision so trip estimates stay honest.</p>
HTML,
            ],
            [
                'slug' => 'petrol-levy-explained',
                'title' => 'Petrol Levy Explained',
                'excerpt' => 'What the petrol levy is, why governments use it, and how levy changes show up at the pump in Pakistan.',
                'category' => 'government',
                'tags' => ['levy', 'tax', 'petrol'],
                'is_featured' => true,
                'reading_minutes' => 5,
                'published_at' => $now->copy()->subDays(2),
                'faq' => [
                    [
                        'question' => 'Is the petrol levy the same as sales tax?',
                        'answer' => 'No. The petrol levy is a specific federal levy on petrol; sales tax is separate. Together with other costs they make up the final retail price.',
                    ],
                ],
                'body' => <<<'HTML'
<p>When headlines say the government “raised the petrol levy,” they mean a federal charge baked into the retail petrol price — not the crude oil price itself.</p>
<h2>What the levy is for</h2>
<p>Governments use fuel levies to raise revenue and sometimes to cushion (or accelerate) pass-through of international prices. In Pakistan the petrol levy has been a major budget tool.</p>
<h2>How it hits the pump</h2>
<p>OGRA’s build-up includes the levy as a fixed rupee-per-litre component (subject to notified levels). Raise the levy and, all else equal, petrol becomes more expensive even if crude is unchanged.</p>
<h2>Why it matters for drivers</h2>
<p>Trip cost and monthly budgets move with levy decisions. After each notification, re-check <a href="/petrol-price-today">petrol today</a> and your saved calculator assumptions.</p>
HTML,
            ],
            [
                'slug' => 'what-is-hobc',
                'title' => 'What Is HOBC?',
                'excerpt' => 'HOBC (high-octane blending component / high-octane petrol) explained for Pakistani drivers — when it helps and when it does not.',
                'category' => 'hobc',
                'tags' => ['HOBC', 'octane', 'petrol'],
                'is_trending' => true,
                'reading_minutes' => 4,
                'published_at' => $now->copy()->subDays(3),
                'faq' => [
                    [
                        'question' => 'Do all cars need HOBC?',
                        'answer' => 'No. Use the octane grade your manufacturer recommends. Higher octane helps engines designed for it; it does not magically add mileage to every car.',
                    ],
                ],
                'body' => <<<'HTML'
<p>HOBC is the higher-octane petrol sold alongside regular motor spirit (MS / PMG) in Pakistan. You will also see brand names like Hi-Octane or Premium at some OMCs.</p>
<h2>Octane in plain terms</h2>
<p>Octane rating measures resistance to knock (premature ignition). Turbo and high-compression engines often specify higher octane. Regular engines usually run fine on standard petrol.</p>
<h2>Price premium</h2>
<p>HOBC costs more per litre. If your handbook says RON 91/95 (or equivalent), paying for HOBC may be required; otherwise you are mostly paying for brand positioning.</p>
<h2>Check live rates</h2>
<p>Compare high-octane listings in the app and see <a href="/fuel/hobc">HOBC overview</a> alongside regular <a href="/petrol-price-today">petrol prices</a>.</p>
HTML,
            ],
            [
                'slug' => 'difference-between-petrol-and-diesel',
                'title' => 'Difference Between Petrol and Diesel',
                'excerpt' => 'Fuel chemistry, engines, cost per km, and which option suits typical Pakistani driving — without the myths.',
                'category' => 'guides',
                'tags' => ['petrol', 'diesel', 'HSD'],
                'reading_minutes' => 5,
                'published_at' => $now->copy()->subDays(4),
                'body' => <<<'HTML'
<p>Petrol and diesel are both refined from crude, but they power different engines and produce different cost-per-kilometre outcomes.</p>
<h2>Engines</h2>
<p>Petrol engines ignite with spark plugs. Diesel engines compress air until fuel ignites. That is why diesel units feel stronger at low RPM and often return better km/L on highways.</p>
<h2>Price vs efficiency</h2>
<p>HSD can cost more per litre than petrol, but higher mileage can still win on long routes. Use the <a href="/trip-cost-calculator">trip cost calculator</a> with realistic mileage for both fuels before deciding.</p>
<h2>Maintenance and use-case</h2>
<p>Diesels shine on sustained highway loads and commercial duty. City stop-go driving, noise preference, and purchase price often favour petrol cars and bikes.</p>
HTML,
            ],
            [
                'slug' => 'weekly-fuel-price-roundup',
                'title' => 'Weekly Fuel Price Roundup',
                'excerpt' => 'How to read the week’s petrol, diesel and LPG moves in Pakistan — and what to watch next.',
                'category' => 'fuel-news',
                'tags' => ['weekly', 'roundup', 'OGRA'],
                'is_featured' => true,
                'is_trending' => true,
                'reading_minutes' => 3,
                'published_at' => $now->copy(),
                'body' => <<<'HTML'
<p>Each week, Fuel Prices PK summarises the direction of petrol, HSD and LPG so you can spot patterns without scrolling every notification.</p>
<h2>What we track</h2>
<p>National reference rates (typically PSO-linked), notable city differences, and whether the week was up, down or flat versus the prior week.</p>
<h2>How to use this page</h2>
<p>Open <a href="/price-history">price history</a> for the 7-day and 30-day charts, then jump to <a href="/petrol-price-today">petrol</a>, <a href="/diesel-price-today">diesel</a> or <a href="/lpg-price-today">LPG</a> for today’s table.</p>
<h2>Stay notified</h2>
<p>Enable price alerts in the app (More → Alerts) so weekly moves reach you even when you are offline the site.</p>
HTML,
            ],
            [
                'slug' => 'opec-explained',
                'title' => 'OPEC Explained',
                'excerpt' => 'What OPEC and OPEC+ do, why production decisions matter, and how that ripples into Pakistani fuel prices.',
                'category' => 'oil-market',
                'tags' => ['OPEC', 'crude', 'oil market'],
                'reading_minutes' => 5,
                'published_at' => $now->copy()->subDays(5),
                'body' => <<<'HTML'
<p>OPEC is a group of oil-exporting countries that coordinate production policy. OPEC+ adds other producers. Their quotas influence global crude supply — and therefore prices.</p>
<h2>Why Pakistanis should care</h2>
<p>Even though Pakistan mostly imports refined products, those products still track crude and regional benchmarks. Sustained OPEC cuts can tighten markets; surplus can ease them.</p>
<h2>Not a one-to-one link</h2>
<p>Pump prices also include FX, levy, refining/import parity and OGRA’s timetable. Crude can fall while petrol stays high if the levy rises, and vice versa.</p>
HTML,
            ],
            [
                'slug' => 'how-crude-oil-affects-pakistani-fuel-prices',
                'title' => 'How Crude Oil Affects Pakistani Fuel Prices',
                'excerpt' => 'From Brent/Dubai benchmarks to the petrol pump — the path crude takes into local prices, with important caveats.',
                'category' => 'oil-market',
                'tags' => ['crude', 'Brent', 'pricing'],
                'is_trending' => true,
                'reading_minutes' => 6,
                'published_at' => $now->copy()->subDays(6),
                'body' => <<<'HTML'
<p>Crude oil is the starting ingredient. Pakistani retail petrol and diesel are finished products whose prices still lean on international oil markets.</p>
<h2>Benchmarks</h2>
<p>Traders watch Brent, WTI and Dubai/Oman. Asian product markets often matter even more for import parity of petrol and gasoil.</p>
<h2>Lag and filters</h2>
<p>There is usually a lag between a crude spike and a pump change. Domestic taxes and the pricing calendar can soften or amplify the move.</p>
<h2>Practical takeaway</h2>
<p>Watch crude for direction, but always verify the <a href="/">live pump price</a> before you fill up.</p>
HTML,
            ],
            [
                'slug' => '10-fuel-saving-tips',
                'title' => '10 Fuel Saving Tips',
                'excerpt' => 'Practical habits that cut litres used — tyre pressure, speed, idling, load and route planning for Pakistani roads.',
                'category' => 'fuel-saving',
                'tags' => ['tips', 'mileage', 'saving'],
                'is_featured' => true,
                'reading_minutes' => 5,
                'published_at' => $now->copy()->subDays(7),
                'faq' => [
                    [
                        'question' => 'What saves the most fuel quickly?',
                        'answer' => 'Correct tyre pressure, smoother acceleration, and avoiding unnecessary idling usually give the fastest real-world gains.',
                    ],
                ],
                'body' => <<<'HTML'
<p>Fuel-saving tips only help if they are realistic. These ten are grounded in everyday Pakistani driving.</p>
<h2>1. Tyre pressure</h2>
<p>Under-inflated tyres raise rolling resistance. Check cold pressures monthly.</p>
<h2>2. Smooth throttle</h2>
<p>Hard launches burn fuel. Ease into traffic; anticipate lights.</p>
<h2>3. Speed discipline</h2>
<p>Aerodynamic drag rises quickly above ~90–100 km/h on highways.</p>
<h2>4. Cut idle time</h2>
<p>Long AC idling at shops burns fuel with zero kilometres.</p>
<h2>5. Lighten the boot</h2>
<p>Extra weight and roof carriers hurt mileage.</p>
<h2>6. Service on time</h2>
<p>Dirty filters and old oil reduce efficiency.</p>
<h2>7. Plan errands</h2>
<p>Combine trips; cold starts are thirstier.</p>
<h2>8. Use AC wisely</h2>
<p>At city speeds, AC vs windows is a trade-off; at highway speeds, AC usually wins.</p>
<h2>9. Right fuel grade</h2>
<p>Do not overspend on HOBC unless the engine needs it.</p>
<h2>10. Measure honestly</h2>
<p>Track km/L with the <a href="/mileage-calculator">mileage calculator</a> after each tank.</p>
HTML,
            ],
            [
                'slug' => 'fuel-cost-calculator-guide',
                'title' => 'Fuel Cost Calculator Guide',
                'excerpt' => 'How to estimate trip fuel cost in Pakistan using distance, mileage and live petrol or diesel rates.',
                'category' => 'calculators',
                'tags' => ['calculator', 'trip cost'],
                'reading_minutes' => 3,
                'published_at' => $now->copy()->subDays(8),
                'body' => <<<'HTML'
<p>The fuel cost calculator turns today’s rate into a rupee estimate for a journey.</p>
<h2>Inputs</h2>
<p>Distance (km), vehicle mileage (km/L), and the fuel price for your city. Litres = distance ÷ mileage. Cost = litres × price.</p>
<h2>Tips for accuracy</h2>
<p>Use real-world mileage, not brochure claims. Re-run after price revisions.</p>
<p><a href="/fuel-cost-calculator">Open the fuel cost calculator</a> or the in-app Calc tab.</p>
HTML,
            ],
            [
                'slug' => 'trip-cost-calculator-guide',
                'title' => 'Trip Cost Calculator Guide',
                'excerpt' => 'Estimate one-way and round-trip petrol or diesel cost before you leave — with live Pakistan rates.',
                'category' => 'calculators',
                'tags' => ['calculator', 'round trip'],
                'reading_minutes' => 3,
                'published_at' => $now->copy()->subDays(9),
                'body' => <<<'HTML'
<p>Trip mode doubles distance for round trips so you can budget fuel both ways.</p>
<h2>When to use it</h2>
<p>Intercity travel, wedding trips, and office weekly runs. Pair with city pages if your departure and destination have different listed rates.</p>
<p><a href="/trip-cost-calculator">Open the trip cost calculator</a>.</p>
HTML,
            ],
        ];
    }
}
