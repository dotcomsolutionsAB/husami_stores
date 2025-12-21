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

                return $this->success(
                    'Data fetched successfully',
                    $counter,
                    200
                );
            }

            // LIST (simple list – no pagination needed unless you want)
            $items = CounterModel::orderBy('id', 'desc')->get();

            return $this->success(
                'Data fetched successfully',
                $items,
                200
            );

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