<?php

namespace Database\Seeders;

use App\Models\Article;
use Illuminate\Database\Seeder;

/**
 * Evergreen Pakistan fuel SEO guides targeting high-intent searches.
 * Idempotent via slug. Keep posts useful — not thin keyword spam.
 */
class SeoFuelGuidesSeeder extends Seeder
{
    public function run(): void
    {
        $day = 10;
        foreach ($this->articles() as $row) {
            $published = now()->subDays($day);
            $day++;

            Article::query()->updateOrCreate(
                ['slug' => $row['slug']],
                $row + [
                    'status' => Article::STATUS_PUBLISHED,
                    'published_at' => $row['published_at'] ?? $published,
                    'author_name' => $row['author_name'] ?? 'Fuel Prices PK',
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function articles(): array
    {
        return [
            $this->guide(
                'petrol-price-today-in-pakistan',
                'Petrol Price Today in Pakistan — Live OGRA Rate',
                'Check today’s petrol (MS / PMG) price in Pakistan, what OGRA notifies, and how to compare Karachi, Lahore and Islamabad.',
                'petrol-prices',
                ['petrol price today Pakistan', 'petrol rate today', 'OGRA petrol', 'MS PMG'],
                true,
                true,
                [
                    ['q' => 'What is the petrol price today in Pakistan?', 'a' => 'Open Fuel Prices PK for the latest national petrol rate and city views. Rates follow OGRA / Petroleum Division notifications under the daily pricing mechanism.'],
                    ['q' => 'Is petrol the same as MS or PMG?', 'a' => 'Yes. Motor Spirit (MS) / Premier Motor Gasoline (PMG) is the regular petrol sold at pumps across Pakistan.'],
                ],
                <<<'HTML'
<p><strong>Petrol price today in Pakistan</strong> is the rate most drivers search every morning. Under daily OGRA pricing, the national ex-depot petrol (MS / PMG) figure can change on working days.</p>
<h2>Where to see today’s rate</h2>
<p>Use our live <a href="/petrol-price-today">petrol price today</a> page for the current national figure, then open city pages such as <a href="/petrol-price/karachi">Karachi</a>, <a href="/petrol-price/lahore">Lahore</a> and <a href="/petrol-price/islamabad">Islamabad</a>.</p>
<h2>What moves the price</h2>
<p>Seven-day Platts averages, the rupee exchange rate, petroleum levy, dealer/OMC margins and IFEM all feed into the notified pump price. See <a href="/guides/why-petrol-prices-change-in-pakistan">why petrol prices change</a>.</p>
<h2>Estimate your fill</h2>
<p>After checking today’s rate, use the <a href="/fuel-cost-calculator">fuel cost calculator</a> or <a href="/trip-cost-calculator">trip cost calculator</a> before you leave.</p>
HTML
            ),
            $this->guide(
                'diesel-hsd-price-today-pakistan',
                'Diesel Price Today in Pakistan (HSD) — Live Rate',
                'Today’s high-speed diesel (HSD) price in Pakistan, why HSD often moves more than petrol, and where to track OGRA updates.',
                'diesel',
                ['diesel price today Pakistan', 'HSD rate today', 'high speed diesel', 'OGRA diesel'],
                true,
                true,
                [
                    ['q' => 'What is HSD?', 'a' => 'High-Speed Diesel is the standard diesel sold for cars, buses, trucks, generators and farm machinery in Pakistan.'],
                    ['q' => 'Why is diesel often more expensive than petrol?', 'a' => 'Import parity, Platts gasoil benchmarks, premiums and tax/levy policy can push HSD above petrol — check today’s table rather than assuming.'],
                ],
                <<<'HTML'
<p><strong>Diesel price today in Pakistan</strong> means the HSD (high-speed diesel) rate notified for pumps nationwide. Transport, agriculture and industry watch this number closely.</p>
<h2>Live HSD rate</h2>
<p>See <a href="/diesel-price-today">diesel price today</a> for the current figure. City views help when you compare fill-ups on intercity routes.</p>
<h2>Petrol vs diesel</h2>
<p>HSD and petrol follow related but separate Platts benchmarks. Read <a href="/guides/difference-between-petrol-and-diesel">petrol vs diesel</a> and track both on the home dashboard.</p>
<h2>Budget a trip</h2>
<p>Use the <a href="/trip-cost-calculator">trip cost calculator</a> with your real km/L — brochure mileage is often optimistic for loaded vans and buses.</p>
HTML
            ),
            $this->guide(
                'lpg-price-today-pakistan',
                'LPG Price Today in Pakistan — Per Kg & Cylinder Guide',
                'LPG rate today in Pakistan explained: per-kg pricing, domestic cylinders, and how LPG differs from petrol and CNG.',
                'lpg',
                ['LPG price today Pakistan', 'LPG rate per kg', 'gas cylinder price Pakistan'],
                true,
                false,
                [
                    ['q' => 'Is LPG priced per litre or per kg?', 'a' => 'LPG in Pakistan is commonly quoted per kilogram. Cylinder cost depends on cylinder size and the per-kg rate.'],
                    ['q' => 'Is LPG the same as CNG?', 'a' => 'No. LPG is liquified petroleum gas (cylinders / some vehicles). CNG is compressed natural gas sold in kg at dedicated stations.'],
                ],
                <<<'HTML'
<p><strong>LPG price today in Pakistan</strong> matters for kitchens, restaurants and some dual-fuel vehicles. Unlike petrol, LPG is usually sold <em>per kg</em>.</p>
<h2>Check today’s LPG</h2>
<p>Open <a href="/lpg-price-today">LPG price today</a> for the latest listed rate. Cylinder retail can vary by distributor even when the reference price is national.</p>
<h2>LPG vs petrol vs CNG</h2>
<p>Petrol powers most cars and bikes. CNG needs a dedicated kit and station network. LPG cylinders dominate domestic cooking. Compare fuels on the <a href="/">Fuel Prices PK home</a> page.</p>
HTML
            ),
            $this->guide(
                'kerosene-sko-price-pakistan',
                'Kerosene Oil Price in Pakistan (SKO) — Today’s Rate',
                'Superior kerosene oil (SKO) price in Pakistan: what SKO is used for and how to track the latest notified rate.',
                'petrol-prices',
                ['kerosene price Pakistan', 'SKO rate today', 'kerosene oil price'],
                false,
                true,
                [
                    ['q' => 'What is SKO?', 'a' => 'SKO means Superior Kerosene Oil — kerosene used for lighting, heating and some industrial uses in Pakistan.'],
                ],
                <<<'HTML'
<p><strong>Kerosene (SKO) price in Pakistan</strong> is notified alongside other petroleum products. Rural households and some industries still rely on kerosene.</p>
<h2>Track SKO with other fuels</h2>
<p>Fuel Prices PK lists kerosene with petrol, HSD and LDO on the national dashboard when sources publish it. After OGRA’s daily mechanism, SKO can revise on working days like petrol and diesel.</p>
<p>Also see <a href="/petrol-price-today">petrol today</a> and <a href="/diesel-price-today">diesel today</a>.</p>
HTML
            ),
            $this->guide(
                'cng-price-in-pakistan',
                'CNG Price in Pakistan — Region I & Region II Explained',
                'CNG rates in Pakistan by region, how they differ from petrol, and when CNG still saves money per kilometre.',
                'guides',
                ['CNG price Pakistan', 'CNG rate today', 'Region I Region II CNG'],
                false,
                false,
                [
                    ['q' => 'Why are there two CNG regions?', 'a' => 'Pakistan uses regional CNG pricing (commonly Region I and Region II) based on gas network and policy zones.'],
                ],
                <<<'HTML'
<p><strong>CNG price in Pakistan</strong> is set differently from petrol and HSD. Stations quote CNG per kg, and rates can differ by region.</p>
<h2>CNG vs petrol cost</h2>
<p>CNG is often cheaper per kilometre when kits are maintained and stations are nearby — but kit cost, boot space and station queues matter. Compare with live <a href="/petrol-price-today">petrol prices</a> before converting a car.</p>
<h2>Availability</h2>
<p>CNG supply and station hours vary by city. Always confirm the local rate at the pump; Fuel Prices PK focuses primarily on liquid fuels (petrol, HSD, LPG) with CNG context for planning.</p>
HTML
            ),
            $this->guide(
                'ogra-daily-fuel-pricing-explained',
                'OGRA Daily Fuel Pricing Explained (Pakistan)',
                'How Pakistan’s daily petrol and diesel pricing works: seven-day Platts average, Friday weekend rule, and what OGRA publishes.',
                'government',
                ['OGRA daily pricing', 'daily petrol price Pakistan', 'Platts seven day average'],
                true,
                true,
                [
                    ['q' => 'Do petrol prices change every day now?', 'a' => 'On working days OGRA can notify new ex-depot petrol and HSD prices. Friday notifications typically stay in force over Saturday and Sunday.'],
                    ['q' => 'What is the seven-day average?', 'a' => 'FOB product prices use a rolling average of about seven working days of Platts Arab Gulf assessments, plus other formula items.'],
                ],
                <<<'HTML'
<p>Pakistan shifted from infrequent fortnightly revisions toward <strong>daily OGRA fuel pricing</strong> so pump rates track international markets faster.</p>
<h2>How the daily formula works</h2>
<p>Petrol and HSD use Platts Arab Gulf benchmarks averaged over recent working days, converted at SBP exchange rates, then adjusted for premiums, duties, IFEM, margins, levy and tax.</p>
<h2>Weekend rule</h2>
<p>Prices notified on Friday generally remain unchanged on Saturday and Sunday. Always verify on <a href="/petrol-price-today">petrol today</a> / <a href="/diesel-price-today">diesel today</a>.</p>
<p>More context: <a href="/guides/why-petrol-prices-change-in-pakistan">why prices change</a> and <a href="/guides/petrol-levy-explained">petrol levy explained</a>.</p>
HTML
            ),
            $this->guide(
                'pso-petrol-price-today',
                'PSO Petrol Price Today in Pakistan',
                'Pakistan State Oil (PSO) petrol and diesel rates today — how PSO relates to OGRA notified prices and where to compare.',
                'petrol-prices',
                ['PSO petrol price today', 'PSO diesel rate', 'Pakistan State Oil price'],
                true,
                false,
                [
                    ['q' => 'Does PSO set a different petrol price?', 'a' => 'OGRA notifies national ex-depot rates. PSO and other OMCs sell against that framework; retail boards should match notified petrol/HSD.'],
                ],
                <<<'HTML'
<p><strong>PSO petrol price today</strong> is what most Pakistanis mean by the national pump rate — PSO is the largest OMC and the headline reference in many apps.</p>
<h2>PSO and OGRA</h2>
<p>OGRA publishes the daily build-up; PSO’s retail page lists Premier, Hi-Cetane, SKO, LDO and more. Fuel Prices PK syncs authority sources so the home screen stays close to the notification.</p>
<p>Compare OMCs on <a href="/omc/pso">PSO</a>, <a href="/omc/shell">Shell</a> and <a href="/omc/attock">APL</a>, or open <a href="/petrol-price-today">petrol today</a>.</p>
HTML
            ),
            $this->guide(
                'shell-vs-pso-vs-apl-petrol',
                'PSO vs Shell vs APL Petrol Price — Which Is Cheaper?',
                'Compare PSO, Shell (Wafi) and APL petrol and diesel in Pakistan — when prices match and when stations differ.',
                'guides',
                ['PSO vs Shell', 'APL petrol price', 'OMC compare Pakistan'],
                true,
                false,
                [
                    ['q' => 'Why do OMCs show the same petrol price?', 'a' => 'Regulated MS and HSD follow OGRA notified rates, so major OMCs usually match on those products.'],
                ],
                <<<'HTML'
<p>Drivers often ask whether <strong>Shell or PSO is cheaper</strong>. For regulated petrol and HSD, OGRA’s notification usually keeps major OMCs aligned.</p>
<h2>Where differences appear</h2>
<p>High-octane / HOBC, promotions, and service quality can differ. Location and queue time also matter more than a one-paisa board gap.</p>
<p>Use in-app <strong>Compare</strong> and OMC pages: <a href="/omc/pso">PSO</a>, <a href="/omc/shell">Shell</a>, <a href="/omc/attock">APL</a>.</p>
HTML
            ),
            $this->cityPetrol('karachi', 'Karachi'),
            $this->cityPetrol('lahore', 'Lahore'),
            $this->cityPetrol('islamabad', 'Islamabad'),
            $this->cityPetrol('rawalpindi', 'Rawalpindi'),
            $this->cityPetrol('faisalabad', 'Faisalabad'),
            $this->cityPetrol('multan', 'Multan'),
            $this->cityPetrol('peshawar', 'Peshawar'),
            $this->cityPetrol('quetta', 'Quetta'),
            $this->cityPetrol('hyderabad', 'Hyderabad'),
            $this->cityPetrol('gujranwala', 'Gujranwala'),
            $this->cityPetrol('sialkot', 'Sialkot'),
            $this->cityPetrol('sukkur', 'Sukkur'),
            $this->guide(
                'how-much-40-litre-petrol-costs-pakistan',
                'How Much Does 40 Litres of Petrol Cost in Pakistan?',
                'Quick way to estimate a 40-litre tank fill from today’s petrol price — with a link to the live calculator.',
                'calculators',
                ['40 litre petrol cost', 'tank fill cost Pakistan', 'petrol tank price'],
                false,
                true,
                [
                    ['q' => 'How do I calculate tank cost?', 'a' => 'Multiply litres by today’s petrol price per litre. Example: 40 × today’s rate = fill cost.'],
                ],
                <<<'HTML'
<p>A common question after every hike: <strong>how much is 40 litres of petrol in Pakistan today?</strong></p>
<h2>Simple formula</h2>
<p><code>Cost = litres × price per litre</code>. If petrol is Rs 327.12/L, then 40 L ≈ Rs 13,085 (before any station rounding).</p>
<h2>Do it live</h2>
<p>Rates change under daily pricing — use the <a href="/fuel-cost-calculator">fuel cost calculator</a> (tank mode) with <a href="/petrol-price-today">today’s petrol price</a> so you never rely on yesterday’s maths.</p>
HTML
            ),
            $this->guide(
                'motorcycle-petrol-cost-pakistan',
                'Motorcycle Petrol Cost in Pakistan — Daily & Monthly',
                'Estimate bike petrol cost using km/day, mileage and today’s petrol rate in Pakistan.',
                'calculators',
                ['motorcycle petrol cost Pakistan', 'bike fuel cost', 'petrol budget motorcycle'],
                false,
                false,
                [
                    ['q' => 'How much petrol does a 70cc bike use?', 'a' => 'Typical small bikes often return roughly 40–55 km/L depending on rider, load and traffic — measure your own tank-to-tank mileage.'],
                ],
                <<<'HTML'
<p>Most Pakistani commuters ask about <strong>motorcycle petrol cost</strong> more than car fills. Small daily distances still add up when petrol rises.</p>
<h2>Estimate</h2>
<p>Daily litres ≈ km per day ÷ km per litre. Monthly cost ≈ daily litres × 30 × today’s petrol rate.</p>
<p>Pull the rate from <a href="/petrol-price-today">petrol today</a>, then use the <a href="/monthly-fuel-budget-calculator">monthly fuel budget calculator</a>.</p>
HTML
            ),
            $this->guide(
                'petrol-price-increase-decrease-pakistan',
                'Petrol Price Increase & Decrease in Pakistan — How to Read Alerts',
                'Understand petrol and diesel hike/cut headlines, paisa vs rupee moves, and how to verify the official new rate.',
                'fuel-news',
                ['petrol price increase Pakistan', 'petrol price decrease', 'fuel hike today'],
                true,
                true,
                [
                    ['q' => 'Where should I verify a fuel hike rumour?', 'a' => 'Check OGRA / Petroleum Division notifications and Fuel Prices PK live tables — social posts are often outdated screenshots.'],
                ],
                <<<'HTML'
<p>Headlines about a <strong>petrol price increase in Pakistan</strong> (or a cut) spread fast. Always confirm the effective date and the exact rupee change.</p>
<h2>How to read a notification</h2>
<p>Look for: product (petrol vs HSD), old rate, new rate, change per litre, and effective date/time. Daily pricing means consecutive working-day moves are possible.</p>
<p>Verify on <a href="/">Fuel Prices PK</a>, skim <a href="/guides">guides &amp; news</a>, and enable alerts in the app.</p>
HTML
            ),
            $this->guide(
                'ifem-explained-pakistan-fuel',
                'IFEM Explained — Inland Freight in Pakistan Fuel Prices',
                'What Inland Freight Equalisation Margin (IFEM) means in the OGRA petrol and diesel price build-up.',
                'government',
                ['IFEM Pakistan', 'inland freight fuel price', 'OGRA IFEM'],
                false,
                false,
                [
                    ['q' => 'Does IFEM make prices the same in every city?', 'a' => 'IFEM equalises inland freight in the notified ex-depot framework so major cities stay close on regulated petrol/HSD.'],
                ],
                <<<'HTML'
<p><strong>IFEM</strong> (Inland Freight Equalisation Margin) is a line item in Pakistan’s fuel price formula. It spreads the cost of moving product from coastal/refinery points across the country.</p>
<h2>Why you care</h2>
<p>Without equalisation, inland cities would systematically pay more freight. IFEM is one reason national petrol and HSD boards stay relatively aligned.</p>
<p>See also <a href="/guides/ogra-daily-fuel-pricing-explained">OGRA daily pricing</a>.</p>
HTML
            ),
            $this->guide(
                'platts-arab-gulf-pakistan-fuel',
                'Platts Arab Gulf & Pakistan Petrol/Diesel Prices',
                'How Platts Arab Gulf petrol and gasoil assessments feed into OGRA’s daily fuel price formula.',
                'oil-market',
                ['Platts Arab Gulf', 'Platts petrol Pakistan', 'import parity fuel'],
                false,
                false,
                [
                    ['q' => 'Is Platts the same as Brent crude?', 'a' => 'No. Brent is a crude benchmark. Platts Arab Gulf assesses refined products (e.g. gasoline 92, gasoil) used in import-parity formulas.'],
                ],
                <<<'HTML'
<p>Pakistan’s daily petrol and HSD prices lean on <strong>Platts Arab Gulf</strong> product assessments — not only crude headlines on TV.</p>
<h2>Products that matter</h2>
<p>Motor spirit often links to gasoline 92 RON assessments; HSD links to gasoil/diesel assessments, plus premiums and freight.</p>
<p>Crude still sets the mood — read <a href="/guides/how-crude-oil-affects-pakistani-fuel-prices">how crude affects local prices</a> — but Platts product numbers are closer to the pump formula.</p>
HTML
            ),
            $this->guide(
                'weekend-fuel-prices-pakistan',
                'Do Petrol Prices Change on Weekends in Pakistan?',
                'Friday–Sunday fuel price rule under OGRA daily pricing — when boards stay frozen and when Monday can move.',
                'fuel-news',
                ['weekend petrol price Pakistan', 'Friday fuel price', 'Saturday Sunday petrol'],
                false,
                true,
                [
                    ['q' => 'Can petrol rise on Saturday?', 'a' => 'Under the daily mechanism, Friday’s notified prices generally remain effective on Saturday and Sunday.'],
                ],
                <<<'HTML'
<p>Under <strong>daily pricing</strong>, many drivers worry about weekend surprises. Policy guidance is that prices notified on <strong>Friday</strong> usually hold through <strong>Saturday and Sunday</strong>.</p>
<h2>What to do Friday night</h2>
<p>Check <a href="/petrol-price-today">petrol</a> and <a href="/diesel-price-today">diesel</a> before the weekend trip. Monday can bring a fresh working-day revision.</p>
HTML
            ),
            $this->guide(
                'petrol-vs-diesel-cost-per-km',
                'Petrol vs Diesel Cost per KM in Pakistan',
                'Compare petrol and HSD on a per-kilometre basis using today’s rates and realistic mileage.',
                'guides',
                ['petrol vs diesel cost per km', 'HSD mileage Pakistan', 'fuel cost per km'],
                false,
                false,
                [
                    ['q' => 'Is diesel always cheaper per km?', 'a' => 'Not always. If HSD is much costlier per litre and your diesel mileage advantage is small in city traffic, petrol can win.'],
                ],
                <<<'HTML'
<p><strong>Cost per km</strong> = price per litre ÷ km per litre. That single number decides whether petrol or diesel wins for <em>your</em> routes.</p>
<h2>Worked approach</h2>
<p>1) Note today’s <a href="/petrol-price-today">petrol</a> and <a href="/diesel-price-today">HSD</a>. 2) Use real tank-to-tank mileage. 3) Compare with the <a href="/trip-cost-calculator">trip calculator</a>.</p>
HTML
            ),
            $this->guide(
                'how-to-check-ogra-fuel-prices',
                'How to Check OGRA Fuel Prices Online (Pakistan)',
                'Step-by-step: OGRA publications, Petroleum Division notices, and faster ways to see today’s petrol and diesel rates.',
                'guides',
                ['check OGRA prices', 'OGRA website fuel price', 'official petrol price Pakistan'],
                false,
                false,
                [
                    ['q' => 'Is OGRA’s PDF the official rate?', 'a' => 'OGRA price publications and Petroleum Division notifications are the official references for ex-depot petrol and HSD.'],
                ],
                <<<'HTML'
<p>To <strong>check OGRA fuel prices</strong>, you can read the regulator’s price publications (often PDFs) or trusted summaries that cite them.</p>
<h2>Faster path</h2>
<p>Fuel Prices PK syncs OGRA / news / PSO sources into one dashboard: <a href="/">home</a>, <a href="/petrol-price-today">petrol today</a>, <a href="/diesel-price-today">diesel today</a>.</p>
<p>For the mechanism itself, read <a href="/guides/ogra-daily-fuel-pricing-explained">OGRA daily pricing explained</a>.</p>
HTML
            ),
            $this->guide(
                'ldo-light-diesel-oil-price-pakistan',
                'Light Diesel Oil (LDO) Price in Pakistan',
                'What LDO is, who uses it, and how its price relates to HSD and OGRA notifications.',
                'diesel',
                ['LDO price Pakistan', 'light diesel oil rate', 'LDO vs HSD'],
                false,
                false,
                [
                    ['q' => 'Is LDO the same as HSD?', 'a' => 'No. LDO (light diesel oil) is a different product, often for industrial/agricultural use, and is priced separately from high-speed diesel.'],
                ],
                <<<'HTML'
<p><strong>LDO (Light Diesel Oil)</strong> appears on OMC price boards alongside HSD and SKO. It is not the same as high-speed diesel for road vehicles.</p>
<p>Track LDO on the Fuel Prices PK specialty list when published, and keep HSD drivers on <a href="/diesel-price-today">diesel price today</a>.</p>
HTML
            ),
            $this->guide(
                'petrol-price-history-pakistan',
                'Petrol Price History in Pakistan — Charts & Trends',
                'How to read petrol and diesel price history in Pakistan: weekly moves, monthly trends and what charts are good for.',
                'petrol-prices',
                ['petrol price history Pakistan', 'diesel price chart', 'fuel price trend'],
                true,
                false,
                [
                    ['q' => 'Where can I see petrol price history?', 'a' => 'Open the Fuel Prices PK price history charts for weekly, monthly and longer views of petrol, HSD and LPG.'],
                ],
                <<<'HTML'
<p><strong>Petrol price history in Pakistan</strong> helps you spot whether this week’s hike is unusual or part of a longer climb.</p>
<p>Use <a href="/price-history">price history</a> plus the daily tables on <a href="/petrol-price-today">petrol today</a>. For narrative context, see the <a href="/guides/weekly-fuel-price-roundup">weekly roundup</a> guide.</p>
HTML
            ),
            $this->guide(
                'what-is-ms-pmg-petrol-pakistan',
                'What Is MS / PMG Petrol in Pakistan?',
                'MS and PMG meaning at Pakistani pumps — regular petrol explained in plain language.',
                'guides',
                ['MS petrol Pakistan', 'PMG meaning', 'Premier Motor Gasoline'],
                false,
                false,
                [
                    ['q' => 'What does PMG stand for?', 'a' => 'PMG commonly means Premier Motor Gasoline — regular petrol. MS means Motor Spirit, the same product family.'],
                ],
                <<<'HTML'
<p>On OGRA sheets and OMC boards you will see <strong>MS</strong> or <strong>PMG</strong> — that is everyday <strong>petrol</strong>, not HOBC.</p>
<p>Check the live rate on <a href="/petrol-price-today">petrol price today</a>. For premium fuel, read <a href="/guides/what-is-hobc">what is HOBC</a>.</p>
HTML
            ),
            $this->guide(
                'fuel-prices-pakistan-urdu-guide',
                'پٹرول کی قیمت آج پاکستان — Petrol Price Today (Urdu Guide)',
                'پاکستان میں آج کے پٹرول، ڈیزل اور ایل پی جی ریٹ کہاں دیکھیں — سادہ اردو رہنمائی۔',
                'guides',
                ['پٹرول کی قیمت آج', 'ڈیزل ریٹ آج', 'petrol price today urdu'],
                true,
                true,
                [
                    ['q' => 'آج کا پٹرول ریٹ کہاں دیکھیں؟', 'a' => 'Fuel Prices PK کی ویب سائٹ یا ایپ کھولیں — Petrol Price Today صفحہ قومی ریٹ دکھاتا ہے۔'],
                ],
                <<<'HTML'
<p><strong>پٹرول کی قیمت آج پاکستان</strong> جاننے کے لیے Fuel Prices PK استعمال کریں۔ قومی ریٹ OGRA نوٹیفکیشن کے مطابق اپڈیٹ ہوتا ہے۔</p>
<h2>روزانہ قیمت</h2>
<p>اب پٹرول اور ڈیزل کی قیمتیں کام کے دنوں میں روزانہ بدل سکتی ہیں۔ <a href="/petrol-price-today">پٹرول آج</a> اور <a href="/diesel-price-today">ڈیزل آج</a> چیک کریں۔</p>
<h2>شہر</h2>
<p>کراچی، لاہور، اسلام آباد کے صفحات بھی دستیاب ہیں — جیسے <a href="/petrol-price/karachi">کراچی پٹرول</a>۔</p>
<p>English deep-dive: <a href="/guides/petrol-price-today-in-pakistan">petrol price today in Pakistan</a>.</p>
HTML
            ),
            $this->guide(
                'best-time-to-fill-petrol-pakistan',
                'Best Time to Fill Petrol in Pakistan',
                'Should you fill before Friday, after a cut, or mid-week? Practical tips under daily OGRA pricing.',
                'fuel-saving',
                ['best time to fill petrol', 'when to fill fuel Pakistan', 'fill up before hike'],
                false,
                true,
                [
                    ['q' => 'Should I fill up before a rumoured hike?', 'a' => 'Only if an official notification is out. Rumours are often wrong; verify the effective date first.'],
                ],
                <<<'HTML'
<p>Under daily pricing, “fill before midnight” advice only works when a <em>confirmed</em> increase takes effect at a known time.</p>
<h2>Practical rule</h2>
<p>Watch the official change, then fill if a hike is locked for tonight. Otherwise buy what you need — panic queues waste time and fuel. Track rates on <a href="/petrol-price-today">petrol today</a>.</p>
HTML
            ),
        ];
    }

    /**
     * @param  list<array{q: string, a: string}>  $faq
     * @param  list<string>  $tags
     * @return array<string, mixed>
     */
    private function guide(
        string $slug,
        string $title,
        string $excerpt,
        string $category,
        array $tags,
        bool $featured,
        bool $trending,
        array $faq,
        string $body,
    ): array {
        $faqRows = array_map(
            fn (array $f) => ['question' => $f['q'], 'answer' => $f['a']],
            $faq,
        );

        return [
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $excerpt,
            'category' => $category,
            'tags' => $tags,
            'is_featured' => $featured,
            'is_trending' => $trending,
            'reading_minutes' => max(3, (int) ceil(str_word_count(strip_tags($body)) / 180)),
            'faq' => $faqRows,
            'meta_title' => mb_strlen($title) > 55 ? mb_substr($title, 0, 52).'…' : $title,
            'meta_description' => $excerpt,
            'body' => $body,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cityPetrol(string $slug, string $name): array
    {
        return $this->guide(
            'petrol-price-in-'.$slug,
            "Petrol Price in {$name} Today — Pakistan",
            "Today’s petrol price in {$name}, Pakistan — live national OGRA-linked rate and local links for diesel and LPG.",
            'petrol-prices',
            [
                "petrol price {$name}",
                "petrol price today {$name}",
                "{$name} petrol rate",
                'Pakistan fuel prices',
            ],
            in_array($slug, ['karachi', 'lahore', 'islamabad'], true),
            in_array($slug, ['karachi', 'lahore', 'islamabad', 'rawalpindi'], true),
            [
                [
                    'q' => "What is the petrol price in {$name} today?",
                    'a' => "Check Fuel Prices PK’s {$name} petrol page for the latest rate. Regulated MS/HSD follow OGRA national notifications; open the city page for local context.",
                ],
                [
                    'q' => "Does {$name} have a different petrol price than the rest of Pakistan?",
                    'a' => 'Notified petrol and HSD are national. Small retail differences can still appear; always confirm the board at your station.',
                ],
            ],
            <<<HTML
<p><strong>Petrol price in {$name} today</strong> follows Pakistan’s OGRA-notified motor spirit rate. Use the city page for a focused view and quick links to diesel and LPG.</p>
<h2>Live {$name} rates</h2>
<p>Open <a href="/petrol-price/{$slug}">petrol price in {$name}</a>, <a href="/diesel-price/{$slug}">diesel in {$name}</a>, and <a href="/lpg-price/{$slug}">LPG in {$name}</a>.</p>
<h2>National context</h2>
<p>Compare with <a href="/petrol-price-today">petrol price today in Pakistan</a> and estimate trip cost with the <a href="/fuel-cost-calculator">fuel calculator</a>.</p>
HTML
        );
    }
}
