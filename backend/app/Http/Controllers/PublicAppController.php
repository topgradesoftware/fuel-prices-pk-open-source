<?php

namespace App\Http\Controllers;

use App\Http\Requests\Web\PublicAppIndexRequest;
use App\Http\Requests\Web\PublicCalculatorRequest;
use App\Services\Fuel\FuelDashboardData;
use Inertia\Inertia;
use Inertia\Response;

class PublicAppController extends Controller
{
    public function __invoke(PublicAppIndexRequest $request, FuelDashboardData $data): Response
    {
        $tab = (string) ($request->validated('tab') ?? 'home');

        return $this->render($data, $tab, 'home');
    }

    public function calculator(PublicCalculatorRequest $request, FuelDashboardData $data): Response
    {
        $calcMode = (string) ($request->validated('mode') ?? 'fuel');

        return $this->render($data, 'calc', 'fuel-calculator', $calcMode);
    }

    public function history(FuelDashboardData $data): Response
    {
        return $this->render($data, 'history', 'price-history');
    }

    private function render(
        FuelDashboardData $data,
        string $tab,
        string $seoPage,
        ?string $calcMode = null,
    ): Response {
        return Inertia::render('Public/App', [
            ...$data->forPublicApp(),
            'initial_tab' => $tab,
            'seo_page' => $seoPage,
            'initial_calc_mode' => $calcMode,
        ]);
    }
}
