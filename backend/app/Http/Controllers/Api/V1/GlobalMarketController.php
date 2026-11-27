<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\OilBenchmark;
use Illuminate\Http\Request;

class GlobalMarketController extends Controller
{
    public function index(Request $request)
    {
        $query = OilBenchmark::query();

        if ($request->has('benchmark')) {
            $query->where('benchmark_type', $request->benchmark);
        }

        $benchmarks = $query->orderBy('date', 'desc')->paginate(50);

        return response()->json([
            'status' => 'success',
            'data' => $benchmarks->items(),
            'meta' => [
                'current_page' => $benchmarks->currentPage(),
                'last_page' => $benchmarks->lastPage(),
                'total' => $benchmarks->total(),
            ]
        ]);
    }
}
