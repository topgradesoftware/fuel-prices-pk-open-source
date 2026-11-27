<?php

namespace App\Http\Middleware;

use App\Services\Content\AiBudgetManager;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $isAdmin = str_starts_with(trim($request->path(), '/'), 'admin');

        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'version' => config('app.version'),
            ],
            'seo' => $isAdmin
                ? [
                    'site_name' => \App\Support\Seo::siteName(),
                    'site_url' => \App\Support\Seo::siteUrl(),
                    'default_description' => '',
                    'default_keywords' => '',
                    'og_image' => \App\Support\Seo::ogImage(),
                    'pages' => [],
                    'home_json_ld' => [],
                ]
                : \App\Support\Seo::inertiaShare($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'ai_limit_alert' => function () use ($request, $isAdmin) {
                if (! $isAdmin || ! $request->user()) {
                    return null;
                }

                return app(AiBudgetManager::class)->adminLimitAlert();
            },
        ];
    }
}
