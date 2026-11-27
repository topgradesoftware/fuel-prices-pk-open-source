<?php

namespace App\Providers;

use App\Contracts\AiDraftProvider;
use App\Contracts\FuelPriceProvider;
use App\Contracts\NotificationProvider;
use App\Events\FuelPriceUpdated;
use App\Listeners\QueuePriceChangeNotification;
use App\Listeners\RunContentAutomation;
use App\Services\Content\Providers\GeminiDraftProvider;
use App\Services\Content\Providers\OpenAiCompatibleDraftProvider;
use App\Services\Content\Providers\TemplateDraftProvider;
use App\Repositories\DeviceRepository;
use App\Repositories\NotificationRepository;
use App\Services\Fuel\CompositeFuelPriceProvider;
use App\Services\Fuel\OgraDailyFuelPriceProvider;
use App\Services\Fuel\ProPakistaniFuelPriceProvider;
use App\Services\Fuel\PsoOfficialFuelPriceProvider;
use App\Services\Fuel\TrackMateFuelPriceProvider;
use App\Services\Maps\GooglePlacesStationService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Notifications\Providers\EmailProvider;
use App\Services\Notifications\Providers\FcmProvider;
use App\Services\Notifications\Providers\SmsProvider;
use App\Services\Notifications\Providers\WebPushProvider;
use App\Services\Notifications\Providers\WhatsAppProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TrackMateFuelPriceProvider::class, function () {
            return new TrackMateFuelPriceProvider(
                (string) config('services.trackmate.base_url', 'https://fuel.trackmate.page/api'),
            );
        });

        $this->app->singleton(PsoOfficialFuelPriceProvider::class, function () {
            return new PsoOfficialFuelPriceProvider(
                (string) config('services.pso.prices_url', 'https://psopk.com/en/fuels/fuel-prices'),
            );
        });

        $this->app->singleton(OgraDailyFuelPriceProvider::class, function () {
            return new OgraDailyFuelPriceProvider(
                (string) config('services.ogra.publications_url', 'https://ogra.org.pk/price-publications'),
            );
        });

        $this->app->singleton(CompositeFuelPriceProvider::class, function ($app) {
            return new CompositeFuelPriceProvider([
                $app->make(OgraDailyFuelPriceProvider::class),
                $app->make(ProPakistaniFuelPriceProvider::class),
                $app->make(PsoOfficialFuelPriceProvider::class),
                $app->make(TrackMateFuelPriceProvider::class),
            ]);
        });

        $this->app->bind(FuelPriceProvider::class, CompositeFuelPriceProvider::class);

        $this->app->singleton(GooglePlacesStationService::class, function () {
            $key = config('services.google.maps_api_key');

            return new GooglePlacesStationService(
                filled($key) ? (string) $key : null,
            );
        });

        $this->app->bind(NotificationProvider::class, FcmProvider::class);

        $this->app->singleton(TemplateDraftProvider::class);
        $this->app->singleton(OpenAiCompatibleDraftProvider::class);
        $this->app->singleton(GeminiDraftProvider::class);
        $this->app->bind(AiDraftProvider::class, function ($app) {
            return match (config('content.ai.provider', 'template')) {
                'gemini' => $app->make(GeminiDraftProvider::class),
                'openai_compatible', 'openai' => $app->make(OpenAiCompatibleDraftProvider::class),
                default => $app->make(TemplateDraftProvider::class),
            };
        });

        $this->app->singleton(NotificationDispatcher::class, function ($app) {
            return new NotificationDispatcher(
                [
                    $app->make(FcmProvider::class),
                    $app->make(WebPushProvider::class),
                    $app->make(SmsProvider::class),
                    $app->make(WhatsAppProvider::class),
                    $app->make(EmailProvider::class),
                ],
                $app->make(DeviceRepository::class),
                $app->make(NotificationRepository::class),
            );
        });
    }

    public function boot(): void
    {
        Event::listen(FuelPriceUpdated::class, QueuePriceChangeNotification::class);
        Event::listen(FuelPriceUpdated::class, RunContentAutomation::class);
    }
}
