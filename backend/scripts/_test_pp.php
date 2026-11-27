<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$provider = app(\App\Services\Fuel\ProPakistaniFuelPriceProvider::class);
echo json_encode($provider->latestPrices(), JSON_PRETTY_PRINT)."\n";
