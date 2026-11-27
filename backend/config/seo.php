<?php

return [

    'site_url' => env('SEO_SITE_URL', env('APP_URL', 'https://oilprice.topgradesoftware.com')),

    'site_name' => env('SEO_SITE_NAME', 'Fuel Prices PK'),

    'default_description' => 'Petrol, diesel (HSD) and LPG prices today in Pakistan — Karachi, Lahore, Islamabad. Compare PSO, Shell and APL; track OGRA updates.',

    'default_keywords' => 'petrol price today Pakistan, پٹرول کی قیمت آج, diesel price today Pakistan, HSD rate today, LPG price today Pakistan, kerosene SKO price Pakistan, CNG price Pakistan, Pakistan fuel prices, petrol price Karachi, petrol price Lahore, petrol price Islamabad, petrol price Rawalpindi, petrol price Faisalabad, petrol price Multan, petrol price Peshawar, OGRA petrol price, OGRA daily pricing, PSO petrol price today, Shell petrol price, APL fuel price, diesel price Karachi, fuel price calculator Pakistan, 40 litre petrol cost, motorcycle petrol cost, petrol pump near me, petrol vs diesel Pakistan',

    'title_template' => '%s | Fuel Prices PK',

    'google_site_verification' => env('SEO_GOOGLE_SITE_VERIFICATION'),

    'bing_site_verification' => env('SEO_BING_SITE_VERIFICATION'),

    'indexnow_key' => env('SEO_INDEXNOW_KEY'),

    'ga_measurement_id' => env('SEO_GA_MEASUREMENT_ID', env('GOOGLE_ANALYTICS_ID')),

    'og_image' => [
        // JPEG kept ~100 KB: WhatsApp's crawler ignores images over ~600 KB.
        'path' => '/og-image.jpg',
        'width' => 1200,
        'height' => 630,
        'alt' => 'Fuel Prices PK — live Pakistan fuel prices',
        'type' => 'image/jpeg',
    ],

    'logo_path' => '/icons/icon-512.png',

    'theme_color' => '#f2a63f',

    'author' => 'TopGrade Software',

    'locale' => 'en_GB',

    'geo' => [
        'region' => 'PK',
        'placename' => 'Pakistan',
    ],

    'social' => [
        'https://topgradesoftware.com',
    ],

    'sitemap' => [
        ['path' => '', 'priority' => 1.0, 'changefreq' => 'hourly'],
        ['path' => 'fuel-calculator', 'priority' => 0.95, 'changefreq' => 'daily'],
        ['path' => 'fuel-cost-calculator', 'priority' => 0.9, 'changefreq' => 'weekly'],
        ['path' => 'trip-cost-calculator', 'priority' => 0.9, 'changefreq' => 'weekly'],
        ['path' => 'mileage-calculator', 'priority' => 0.9, 'changefreq' => 'weekly'],
        ['path' => 'monthly-fuel-budget-calculator', 'priority' => 0.9, 'changefreq' => 'weekly'],
        ['path' => 'guides', 'priority' => 0.92, 'changefreq' => 'daily'],
        ['path' => 'price-history', 'priority' => 0.9, 'changefreq' => 'daily'],
        ['path' => 'faq', 'priority' => 0.7, 'changefreq' => 'monthly'],
        ['path' => 'prices', 'priority' => 0.85, 'changefreq' => 'daily'],
        ['path' => 'petrol-price-today', 'priority' => 0.95, 'changefreq' => 'hourly'],
        ['path' => 'diesel-price-today', 'priority' => 0.95, 'changefreq' => 'hourly'],
        ['path' => 'lpg-price-today', 'priority' => 0.95, 'changefreq' => 'hourly'],
        ['path' => 'privacy', 'priority' => 0.3, 'changefreq' => 'yearly'],
        ['path' => 'terms', 'priority' => 0.3, 'changefreq' => 'yearly'],
        ['path' => 'about', 'priority' => 0.4, 'changefreq' => 'yearly'],
        ['path' => 'contact', 'priority' => 0.4, 'changefreq' => 'yearly'],
        ['path' => 'disclaimer', 'priority' => 0.35, 'changefreq' => 'yearly'],
        ['path' => 'editorial-policy', 'priority' => 0.4, 'changefreq' => 'yearly'],
    ],

    'robots_disallow' => [
        '/admin/',
        '/api/',
        '/sanctum/',
        '/up',
    ],

    'robots_allow_extra' => [
        '/guides',
        '/guides/',
    ],

    'pages' => [
        'home' => [
            // Keep ≤42 chars so Inertia can append " | Fuel Prices PK" within ~60.
            'title' => 'Petrol Price Today Pakistan | HSD & LPG',
            'h1' => 'Petrol, diesel & LPG prices in Pakistan today',
            // Keep ≤160 chars (SERP snippet budget).
            'description' => 'Petrol, diesel (HSD) and LPG prices today in Pakistan — Karachi, Lahore, Islamabad. Compare PSO, Shell and APL; track OGRA updates.',
            'keywords' => 'petrol price today Pakistan, پٹرول کی قیمت آج, petrol rate today, diesel price today Pakistan, HSD price today, LPG price today Pakistan, kerosene price Pakistan, CNG rate Pakistan, petrol price Karachi, petrol price Lahore, petrol price Islamabad, petrol price Rawalpindi, petrol price Faisalabad, petrol price Multan, petrol price Peshawar, petrol price Quetta, OGRA daily fuel prices, PSO petrol price today, PSO vs Shell vs APL, diesel price Karachi, 40 litre petrol cost Pakistan, motorcycle petrol cost, fuel cost calculator Pakistan, nearby petrol pump Pakistan',
            'robots' => 'index, follow',
            'json_ld' => 'home',
        ],
        'faq' => [
            'title' => 'Pakistan Fuel Price FAQ | Petrol, Diesel & LPG',
            'h1' => 'Pakistan fuel prices — frequently asked questions',
            'description' => 'Answers about petrol price today in Pakistan, diesel (HSD) and LPG rates, OGRA updates, OMC compare, and how Fuel Prices PK works.',
            'keywords' => 'petrol price today Pakistan FAQ, OGRA fuel prices, diesel HSD Pakistan',
            'robots' => 'index, follow',
            'breadcrumb' => 'FAQ',
        ],
        'fuel-calculator' => [
            'title' => 'Petrol & Diesel Cost Calculator Pakistan | Trip Fuel Estimate',
            'h1' => 'Pakistan fuel cost calculator',
            'description' => 'Estimate petrol or diesel cost for trips, tank fills and daily commuting in Pakistan. Uses live PSO, Shell and APL rates with mileage and distance inputs.',
            'keywords' => 'petrol calculator Pakistan, diesel cost calculator, fuel mileage calculator Pakistan, trip fuel cost, tank fill calculator',
            'robots' => 'index, follow',
            'breadcrumb' => 'Fuel calculator',
            'faq' => [
                [
                    'question' => 'How does the Pakistan fuel cost calculator work?',
                    'answer' => 'Enter distance and vehicle mileage (km per litre). Fuel Prices PK multiplies litres needed by today’s petrol or diesel rate for your selected city to estimate trip cost.',
                ],
                [
                    'question' => 'Can I calculate tank fill cost?',
                    'answer' => 'Yes. Switch to tank mode, enter tank capacity and current fuel level, and the calculator estimates litres to fill and total cost at current rates.',
                ],
                [
                    'question' => 'Which fuel prices does the calculator use?',
                    'answer' => 'It uses live rates from Fuel Prices PK (OMC / OGRA-linked feeds). Select your city when available for local figures, or use national rates.',
                ],
            ],
        ],
        'price-history' => [
            'title' => 'Petrol & Diesel Price History Pakistan | Charts & Trends',
            'h1' => 'Pakistan fuel price history',
            'description' => 'Track petrol, diesel (HSD) and LPG price history in Pakistan with weekly, monthly and yearly charts. Spot OGRA-linked trends before you fill up.',
            'keywords' => 'petrol price history Pakistan, diesel price chart Pakistan, HSD rate history, LPG price trend, OGRA fuel price history',
            'robots' => 'index, follow',
            'breadcrumb' => 'Price history',
            'faq' => [
                [
                    'question' => 'How often is fuel price history updated?',
                    'answer' => 'History charts refresh when Fuel Prices PK syncs new OMC / OGRA-linked prices. Check weekly, monthly or yearly views for longer trends.',
                ],
                [
                    'question' => 'Which fuels can I view in history?',
                    'answer' => 'You can focus on petrol, high-speed diesel (HSD), LPG, or view all available fuels together on the history charts.',
                ],
            ],
        ],
        'privacy' => [
            'title' => 'Privacy Policy | Fuel Prices PK',
            'h1' => 'Privacy Policy',
            'description' => 'How Fuel Prices PK collects, uses and protects device, account and location data for fuel price alerts and app features.',
            'keywords' => 'Fuel Prices PK privacy, data protection Pakistan fuel app',
            'robots' => 'index, follow',
            'breadcrumb' => 'Privacy Policy',
        ],
        'terms' => [
            'title' => 'Terms of Service | Fuel Prices PK',
            'h1' => 'Terms of Service',
            'description' => 'Terms of use for Fuel Prices PK — informational fuel prices, calculators and station discovery from TopGrade Software.',
            'keywords' => 'Fuel Prices PK terms, fuel price app terms of service',
            'robots' => 'index, follow',
            'breadcrumb' => 'Terms of Service',
        ],
        'about' => [
            'title' => 'About Us | Fuel Prices PK',
            'h1' => 'About Us',
            'description' => 'About Fuel Prices PK by TopGrade Software — free Pakistan petrol, diesel and LPG prices, calculators and guides.',
            'keywords' => 'about Fuel Prices PK, TopGrade Software fuel app',
            'robots' => 'index, follow',
            'breadcrumb' => 'About Us',
        ],
        'contact' => [
            'title' => 'Contact | Fuel Prices PK',
            'h1' => 'Contact',
            'description' => 'Contact Fuel Prices PK / TopGrade Software for support, corrections, privacy requests and press.',
            'keywords' => 'contact Fuel Prices PK, support email fuel prices Pakistan',
            'robots' => 'index, follow',
            'breadcrumb' => 'Contact',
        ],
        'disclaimer' => [
            'title' => 'Disclaimer | Fuel Prices PK',
            'h1' => 'Disclaimer',
            'description' => 'Fuel Prices PK disclaimer — informational OGRA-linked rates, calculators and guides; confirm at the pump.',
            'keywords' => 'Fuel Prices PK disclaimer, informational fuel prices',
            'robots' => 'index, follow',
            'breadcrumb' => 'Disclaimer',
        ],
        'editorial-policy' => [
            'title' => 'Editorial Policy | Fuel Prices PK',
            'h1' => 'Editorial Policy',
            'description' => 'How Fuel Prices PK sources, reviews and corrects Pakistan fuel price guides and explainers.',
            'keywords' => 'Fuel Prices PK editorial policy, fuel guide authorship',
            'robots' => 'index, follow',
            'breadcrumb' => 'Editorial Policy',
        ],
    ],

    'home_faq' => [
        [
            'question' => 'What is the petrol price today in Pakistan?',
            'answer' => 'Open Fuel Prices PK to see today’s petrol, diesel (HSD) and LPG rates. Select your city (for example Karachi, Lahore or Islamabad) to view local figures when available, or national OGRA-linked rates.',
        ],
        [
            'question' => 'Do petrol and diesel prices change every day?',
            'answer' => 'Under OGRA’s daily pricing mechanism, petrol and HSD can be revised on working days. Friday notifications typically remain unchanged on Saturday and Sunday.',
        ],
        [
            'question' => 'Where do Pakistan fuel prices come from?',
            'answer' => 'Prices are synced from OGRA publications, Petroleum Division / news confirmations, and public OMC feeds (for example PSO). Figures are informational and can lag by minutes to hours.',
        ],
        [
            'question' => 'What is the diesel (HSD) price today?',
            'answer' => 'Open the Diesel Price Today page or switch to HSD on the home dashboard for the latest high-speed diesel rate in Pakistan.',
        ],
        [
            'question' => 'Can I compare PSO, Shell and APL?',
            'answer' => 'Yes. Open Compare to see PSO, Shell, APL and other OMCs for your selected city and fuel type. Regulated petrol and HSD usually match the OGRA notified rate.',
        ],
        [
            'question' => 'Do you show LPG, kerosene (SKO) and CNG?',
            'answer' => 'Yes for LPG and kerosene/SKO when published. CNG is regional and station-specific — we explain Region I / II in our CNG guide while focusing live tables on liquid fuels.',
        ],
        [
            'question' => 'How much does 40 litres of petrol cost?',
            'answer' => 'Multiply 40 by today’s petrol price per litre, or use the Fuel Cost Calculator tank mode for an instant estimate after each OGRA update.',
        ],
        [
            'question' => 'How do nearby petrol pumps work?',
            'answer' => 'With location permission, the app uses Google Places (via our server) to list gas stations near you.',
        ],
        [
            'question' => 'Is Fuel Prices PK free?',
            'answer' => 'Yes. The web app, PWA and guides are free for checking prices, history, stations and trip cost calculators.',
        ],
    ],

];
