<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GovernmentPeriod;
use Illuminate\Http\Request;

class GovernmentController extends Controller
{
    public function index(Request $request)
    {
        $governments = GovernmentPeriod::orderBy('start_date', 'desc')->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $governments->items(),
            'meta' => [
                'current_page' => $governments->currentPage(),
                'last_page' => $governments->lastPage(),
                'total' => $governments->total(),
            ]
        ]);
    }
}
