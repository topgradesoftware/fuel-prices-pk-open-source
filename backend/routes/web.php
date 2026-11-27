<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\AutomationController;
use App\Http\Controllers\Admin\OpsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\PublicAppController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SeoLandingController;
use App\Http\Controllers\Timeline\FuelHistoryController;
use App\Http\Controllers\Timeline\EconomicIndicatorsController;
use App\Http\Controllers\Timeline\GovernmentTimelineController;
use App\Http\Controllers\Timeline\GlobalMarketsController;
use Illuminate\Support\Facades\Route;

// SEO (dynamic; also published to public/ via seo:publish-static)
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/sitemap-pages.xml', [SeoController::class, 'sitemapPages'])->name('seo.sitemap.pages');
Route::get('/sitemap-images.xml', [SeoController::class, 'sitemapImages'])->name('seo.sitemap.images');
Route::get('/sitemap-news.xml', [SeoController::class, 'sitemapNews'])->name('seo.sitemap.news');
// Common GSC typos / aliases → canonical sitemap
Route::redirect('/Sitemaps', '/sitemap.xml', 301);
Route::redirect('/sitemaps', '/sitemap.xml', 301);
Route::redirect('/sitemap', '/sitemap.xml', 301);
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('seo.robots');
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('seo.llms');

// Indexable SEO landings (city / fuel / OMC / FAQ / calculators)
Route::get('/faq', [SeoLandingController::class, 'faq'])->name('seo.faq');
Route::get('/prices', [SeoLandingController::class, 'prices'])->name('seo.prices');
Route::get('/petrol-price-today', fn () => app(SeoLandingController::class)->hub('petrol'))->name('seo.hub.petrol');
Route::get('/diesel-price-today', fn () => app(SeoLandingController::class)->hub('hsd'))->name('seo.hub.diesel');
Route::get('/lpg-price-today', fn () => app(SeoLandingController::class)->hub('lpg'))->name('seo.hub.lpg');
Route::get('/petrol-price/{city}', fn (string $city) => app(SeoLandingController::class)->cityFuel('petrol', $city))->name('seo.city.petrol');
Route::get('/diesel-price/{city}', fn (string $city) => app(SeoLandingController::class)->cityFuel('hsd', $city))->name('seo.city.diesel');
Route::get('/lpg-price/{city}', fn (string $city) => app(SeoLandingController::class)->cityFuel('lpg', $city))->name('seo.city.lpg');
Route::get('/omc/{company}', [SeoLandingController::class, 'omc'])->name('seo.omc');
Route::get('/fuel/{fuel}', [SeoLandingController::class, 'fuelType'])->name('seo.fuel');
Route::get('/fuel-cost-calculator', fn () => app(SeoLandingController::class)->calculator('fuel-cost-calculator'))->name('seo.calc.fuel');
Route::get('/trip-cost-calculator', fn () => app(SeoLandingController::class)->calculator('trip-cost-calculator'))->name('seo.calc.trip');
Route::get('/mileage-calculator', fn () => app(SeoLandingController::class)->calculator('mileage-calculator'))->name('seo.calc.mileage');
Route::get('/monthly-fuel-budget-calculator', fn () => app(SeoLandingController::class)->calculator('monthly-fuel-budget-calculator'))->name('seo.calc.budget');

// Content hub
Route::get('/guides', [GuideController::class, 'index'])->name('guides.index');
Route::get('/guides/rss.xml', [GuideController::class, 'rss'])->name('guides.rss');
Route::get('/guides/{slug}', [GuideController::class, 'show'])->name('guides.show');

// Economic Timeline
Route::get('/fuel-history', [FuelHistoryController::class, 'index'])->name('timeline.fuel-history');
Route::get('/economic-indicators', [EconomicIndicatorsController::class, 'index'])->name('timeline.economic-indicators');
Route::get('/government-timeline', [GovernmentTimelineController::class, 'index'])->name('timeline.government-timeline');
Route::get('/global-markets', [GlobalMarketsController::class, 'index'])->name('timeline.global-markets');

// Public mobile app (drivers / everyday users)
Route::get('/', PublicAppController::class)->name('home');
Route::get('/fuel-calculator', [PublicAppController::class, 'calculator'])->name('app.calculator');
Route::get('/price-history', [PublicAppController::class, 'history'])->name('app.history');
Route::get('/privacy', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/about', [LegalController::class, 'about'])->name('legal.about');
Route::get('/contact', [LegalController::class, 'contact'])->name('legal.contact');
Route::get('/disclaimer', [LegalController::class, 'disclaimer'])->name('legal.disclaimer');
Route::get('/editorial-policy', [LegalController::class, 'editorial'])->name('legal.editorial');

// Flat keyword aliases (after canonical routes): /petrol-price-karachi → /petrol-price/karachi
Route::get('/{flat}', [SeoLandingController::class, 'flatCityAlias'])
    ->where('flat', '(petrol|diesel|lpg|hobc)-price-.+')
    ->name('seo.city.flat');

// Serve PWA manifest with the correct MIME (hosts often send octet-stream for .webmanifest).
Route::get('/manifest.webmanifest', function () {
    $path = resource_path('pwa/manifest.webmanifest');
    abort_unless(is_file($path), 404);

    return response(file_get_contents($path), 200, [
        'Content-Type' => 'application/manifest+json; charset=utf-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('pwa.manifest');

// Staff ops only
Route::prefix('admin')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.ops'));

    Route::get('/login', [AuthController::class, 'create'])->name('admin.login');
    Route::post('/login', [AuthController::class, 'store'])->name('admin.login.store');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('admin.logout');

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/ops', [OpsController::class, 'index'])->name('admin.ops');
        Route::post('/ops/sync', [OpsController::class, 'sync'])->name('admin.ops.sync');
        Route::post('/ops/companies/{company}/toggle', [OpsController::class, 'toggleCompany'])->name('admin.ops.companies.toggle');
        Route::post('/ops/prices', [OpsController::class, 'storeManualPrice'])->name('admin.ops.prices.store');

        Route::get('/automation', [AutomationController::class, 'index'])->name('admin.automation');
        Route::post('/automation/detect', [AutomationController::class, 'detect'])->name('admin.automation.detect');
        Route::post('/automation/articles/{article}/publish', [AutomationController::class, 'publish'])->name('admin.automation.publish');
        Route::post('/automation/articles/{article}/archive', [AutomationController::class, 'archive'])->name('admin.automation.archive');
        Route::post('/automation/articles/{article}/regenerate', [AutomationController::class, 'regenerate'])->name('admin.automation.regenerate');
        Route::post('/automation/articles/{article}/edit', [AutomationController::class, 'saveHumanEdit'])->name('admin.automation.edit');
    });

    // Owner only: full user management
    Route::middleware(['auth', 'owner'])->group(function () {
        Route::get('/users', [UsersController::class, 'index'])->name('admin.users');
        Route::post('/users', [UsersController::class, 'store'])->name('admin.users.store');
        Route::post('/users/{user}/toggle-admin', [UsersController::class, 'toggleAdmin'])->name('admin.users.toggle-admin');
        Route::post('/users/{user}/reset-password', [UsersController::class, 'resetPassword'])->name('admin.users.reset-password');
        Route::delete('/users/{user}', [UsersController::class, 'destroy'])->name('admin.users.destroy');
    });
});
