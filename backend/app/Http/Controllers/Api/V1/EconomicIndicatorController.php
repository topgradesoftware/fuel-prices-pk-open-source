<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\EconomicIndicator;
use Illuminate\Http\Request;

class EconomicIndicatorController extends Controller
{
    public function index(Request $request)
    {
        $query = EconomicIndicator::query();

        if ($request->has('type')) {
            $query->where('indicator_type', $request->type);
        }

        $indicators = $query->orderBy('date', 'desc')->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $indicators->items(),
            'meta' => [
                'current_page' => $indicators->currentPage(),
                'last_page' => $indicators->lastPage(),
                'total' => $indicators->total(),
            ]
        ]);
    }
}
