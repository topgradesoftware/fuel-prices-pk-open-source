<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FuelPriceHistory;
use Illuminate\Http\Request;

class FuelPriceController extends Controller
{
    public function index(Request $request)
    {
        $query = FuelPriceHistory::query();

        if ($request->has('start_date')) {
            $query->where('effective_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('effective_at', '<=', $request->end_date);
        }

        $prices = $query->orderBy('effective_at', 'desc')->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $prices->items(),
            'meta' => [
                'current_page' => $prices->currentPage(),
                'last_page' => $prices->lastPage(),
                'total' => $prices->total(),
                'disclaimer' => 'Data is presented as published by named sources. Platform draws no conclusions.'
            ]
        ]);
    }
}
