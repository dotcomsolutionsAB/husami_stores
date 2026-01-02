<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MasterDataController extends Controller
{
    use ApiResponse;

    /**
     * Generic fetch for view tables: v_grades, v_items, v_sizes, v_racks
     * Supports: search, limit, offset
     */
    private function fetchFromView(Request $request, string $viewName)
    {
        $limit  = max(1, (int) $request->input('limit', 50));
        $offset = max(0, (int) $request->input('offset', 0));
        $search = trim((string) $request->input('search', ''));

        $q = DB::table($viewName)->select('id', 'name')->orderBy('name', 'asc');

        if ($search !== '') {
            $q->where('name', 'like', "%{$search}%");
        }

        $total = (clone $q)->count();

        $items = $q->skip($offset)->take($limit)->get();
        $count = $items->count();

        return $this->success('Data fetched successfully', $items, 200, [
            'pagination' => [
                'limit'  => $limit,
                'offset' => $offset,
                'count'  => $count,
                'total'  => $total,
            ],
        ]);
    }

    public function grades(Request $request)
    {
        try {
            return $this->fetchFromView($request, 'v_grades');
        } catch (\Throwable $e) {
            return $this->serverError($e, 'Grades fetch failed');
        }
    }

    public function items(Request $request)
    {
        try {
            return $this->fetchFromView($request, 'v_items');
        } catch (\Throwable $e) {
            return $this->serverError($e, 'Items fetch failed');
        }
    }

    public function sizes(Request $request)
    {
        try {
            return $this->fetchFromView($request, 'v_sizes');
        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sizes fetch failed');
        }
    }

    public function racks(Request $request)
    {
        try {
            return $this->fetchFromView($request, 'v_racks');
        } catch (\Throwable $e) {
            return $this->serverError($e, 'Racks fetch failed');
        }
    }

    public function finishes(Request $request)
    {
        try {
            return $this->fetchFromView($request, 'v_finishes');
        } catch (\Throwable $e) {
            return $this->serverError($e, 'Finishes fetch failed');
        }
    }

    public function specifications(Request $request)
    {
        try {
            return $this->fetchFromView($request, 'v_specifications');
        } catch (\Throwable $e) {
            return $this->serverError($e, 'Specifications fetch failed');
        }
    }
}
