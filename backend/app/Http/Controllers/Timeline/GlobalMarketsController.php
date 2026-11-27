<?php

namespace App\Http\Controllers\Timeline;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;

class GlobalMarketsController extends Controller
{
    public function index()
    {
        // TODO: Pass actual global oil benchmarks
        return Inertia::render('Timeline/GlobalMarkets', [
            'markets' => []
        ]);
    }
}
