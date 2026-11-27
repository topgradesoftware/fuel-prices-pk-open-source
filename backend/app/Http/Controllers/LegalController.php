<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    private function shared(): array
    {
        return [
            'updated' => '2026-07-24',
            'app_name' => config('app.name', 'Fuel Prices PK'),
            'contact_email' => env('SUPPORT_EMAIL', 'support@topgradesoftware.com'),
            'company_name' => 'TopGrade Software',
            'company_url' => 'https://topgradesoftware.com',
        ];
    }

    public function privacy(): Response
    {
        return Inertia::render('Legal/Privacy', $this->shared());
    }

    public function terms(): Response
    {
        return Inertia::render('Legal/Terms', $this->shared());
    }

    public function about(): Response
    {
        return Inertia::render('Legal/About', $this->shared());
    }

    public function contact(): Response
    {
        return Inertia::render('Legal/Contact', $this->shared());
    }

    public function disclaimer(): Response
    {
        return Inertia::render('Legal/Disclaimer', $this->shared());
    }

    public function editorial(): Response
    {
        return Inertia::render('Legal/Editorial', $this->shared());
    }
}
