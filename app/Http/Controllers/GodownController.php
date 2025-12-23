<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\GodownModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class GodownController extends Controller
{
    use ApiResponse;

    // CREATE
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $godown = GodownModel::create($validator->validated());

            return $this->success(
                'Godown created successfully.',
                $godown,
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Godown create failed');
        }
    }

    // FETCH
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $godown = GodownModel::find($id);

                if (!$godown) {
                    return $this->error('Godown not found.', 404);
                }

                return $this->success(
                    'Data fetched successfully',
                    $godown,
                    200
                );
            }

            // LIST (simple list)
            $items = GodownModel::orderBy('id', 'desc')->get();

            return $this->success(
                'Data fetched successfully',
                $items,
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Godown fetch failed');
        }
    }

    // EDIT
    public function edit(Request $request, $id)
    {
        try {
            $godown = GodownModel::find($id);

            if (!$godown) {
                return $this->error('Godown not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => ['required', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $godown->update($validator->validated());

            return $this->success(
                'Godown updated successfully.',
                $godown->fresh(),
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Godown update failed');
        }
    }

    // DELETE
    public function delete($id)
    {
        try {
            $godown = GodownModel::find($id);

            if (!$godown) {
                return $this->error('Godown not found.', 404);
            }

            $godown->delete();

            return $this->success(
                'Godown deleted successfully.',
                [],
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Godown deleted failed');
        }
    }
}