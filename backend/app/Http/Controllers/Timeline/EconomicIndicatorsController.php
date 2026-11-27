<?php

namespace App\Http\Controllers\Timeline;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;

class EconomicIndicatorsController extends Controller
{
    public function index()
    {
        // TODO: Pass actual economic indicators from DB
        return Inertia::render('Timeline/EconomicIndicators', [
            'indicators' => []
        ]);
    }
}
