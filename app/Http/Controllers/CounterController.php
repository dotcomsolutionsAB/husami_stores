<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\CounterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class CounterController extends Controller
{
    use ApiResponse;

    // CREATE
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'    => ['required', 'string', 'max:255', 'unique:t_counter,name'],
                'prefix'  => ['nullable', 'string', 'max:32'],
                'number' => ['required', 'integer', 'min:0'],
                'postfix' => ['nullable', 'string', 'max:32'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $counter = CounterModel::create($validator->validated());

            return $this->success(
                'Counter created successfully.',
                $counter,
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Counter create failed');
        }
    }

    // FETCH
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $counter = CounterModel::find($id);

                if (!$counter) {
                    return $this->error('Counter not found.', 404);
                }

                $num = (string)($counter->number ?? 0);
                $num = (strlen($num) >= 4) ? $num : str_pad($num, 4, '0', STR_PAD_LEFT);
                $counter->formatted = ($counter->prefix ?? '') . $num . ($counter->postfix ?? '');

                return $this->success('Data fetched successfully', $counter, 200);
            }

            // LIST + optional search + pagination
            $validator = Validator::make($request->all(), [
                'search' => ['nullable', 'string', 'max:255'],
                'limit'  => ['nullable', 'integer', 'min:1', 'max:200'],
                'offset' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) return $this->validation($validator);

            $search = trim((string)$request->input('search', ''));
            $limit  = max(1, (int)$request->input('limit', 50));
            $offset = max(0, (int)$request->input('offset', 0));

            $itemsQuery = CounterModel::query()->orderBy('id', 'desc');

            if ($search !== '') {
                $itemsQuery->where('name', 'like', "%{$search}%");
            }

            $total = (clone $itemsQuery)->count();

            $items = $itemsQuery->skip($offset)->take($limit)->get();

            $items = $items->map(function ($c) {
                $num = (string)($c->number ?? 0);
                $num = (strlen($num) >= 4) ? $num : str_pad($num, 4, '0', STR_PAD_LEFT);
                $c->formatted = (string)($c->prefix ?? '') . $num . (string)($c->postfix ?? '');
                return $c;
            });

            return $this->success('Data fetched successfully', $items, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $items->count(),
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Counter fetch failed');
        }
    }

    // EDIT
    public function edit(Request $request, $id)
    {
        try {
            $counter = CounterModel::find($id);

            if (!$counter) {
                return $this->error('Counter not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name'    => ['required', 'string', 'max:255', 'unique:t_counter,name,' . $id],
                'prefix'  => ['nullable', 'string', 'max:32'],
                'number' => ['required', 'integer', 'min:0'],
                'postfix' => ['nullable', 'string', 'max:32'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $counter->update($validator->validated());

            return $this->success(
                'Counter updated successfully.',
                $counter->fresh(),
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Counter update failed');
        }
    }

    // DELETE
    public function delete($id)
    {
        try {
            $counter = CounterModel::find($id);

            if (!$counter) {
                return $this->error('Counter not found.', 404);
            }

            $counter->delete();

            return $this->success(
                'Counter deleted successfully.',
                [],
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Counter delete failed');
        }
    }
}