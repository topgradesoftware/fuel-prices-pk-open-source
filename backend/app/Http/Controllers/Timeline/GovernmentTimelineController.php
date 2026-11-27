<?php

namespace App\Http\Controllers\Timeline;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;

class GovernmentTimelineController extends Controller
{
    public function index()
    {
        // TODO: Pass actual government periods and calculated stats
        return Inertia::render('Timeline/GovernmentTimeline', [
            'governments' => []
        ]);
    }
}
