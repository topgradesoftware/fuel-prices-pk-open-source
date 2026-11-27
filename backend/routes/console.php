<?php

use App\Services\Auth\GoogleIdTokenVerifier;
use Illuminate\Support\Facades\Schedule;

// Run inline so sync does not depend on a queue worker / Horizon.
// Every 15 min: price revisions land on PSO's site at unpredictable times
// after the midnight announcement, and users expect the app to catch up fast.
// A no-change sync is cheap (~30 rows compared, nothing written).
Schedule::command('fuel:sync')
    ->everyFifteenMinutes()
    ->name('fuel-sync')
    ->withoutOverlapping(14);

// Fallback SEO refresh if fuel:sync was skipped / failed mid-run.
Schedule::command('seo:publish-static')
    ->hourlyAt(20)
    ->name('seo-publish-static')
    ->withoutOverlapping(20);

Schedule::command('seo:notify')
    ->hourlyAt(25)
    ->name('seo-notify')
    ->withoutOverlapping(20);

// Content automation: weekly / monthly summaries + freshness refresh.
Schedule::command('content:weekly-summary')
    ->weeklyOn(1, '07:30')
    ->name('content-weekly-summary')
    ->withoutOverlapping(60);

Schedule::command('content:monthly-summary')
    ->monthlyOn(1, '08:00')
    ->name('content-monthly-summary')
    ->withoutOverlapping(60);

Schedule::command('content:refresh')
    ->dailyAt('05:40')
    ->name('content-refresh')
    ->withoutOverlapping(40);

// Data-quality: heal previous_price / composite junk even if sync was a no-op.
Schedule::command('prices:clean')
    ->hourlyAt(5)
    ->name('prices-clean')
    ->withoutOverlapping(20);

// Data-quality watchdog: stale prices, failing syncs, source disagreements.
Schedule::command('fuel:watchdog')
    ->hourlyAt(40)
    ->name('fuel-watchdog')
    ->withoutOverlapping(20);

// Keep Google OAuth certs warm so web login is not blocked by a cold fetch.
Schedule::call(function () {
    app(GoogleIdTokenVerifier::class)->warm();
})->hourly()->name('google-oauth-certs-warm')->withoutOverlapping(10);

// Nightly database backup (price history is irreplaceable time-series data),
// followed by retention cleanup. Runs in the quiet window (PKT early morning).
Schedule::command('backup:run --only-db')
    ->dailyAt('03:10')
    ->name('db-backup')
    ->withoutOverlapping(30)
    ->onOneServer();

Schedule::command('backup:clean')
    ->dailyAt('04:00')
    ->name('db-backup-clean')
    ->withoutOverlapping(30)
    ->onOneServer();
