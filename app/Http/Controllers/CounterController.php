<?php

namespace App\Http\Controllers;
use App\Models\CounterModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class CounterController extends Controller
{
    //
    // Create a new Counter
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation
            $request->validate([
                'name' => 'required|string|max:255|unique:t_counter,name',  // Validate the name to be unique
                'prefix' => 'nullable|string|max:32',
                'postfix' => 'nullable|string|max:32',
            ]);

            // 2️⃣ Create the Counter
            $counter = CounterModel::create([
                'name' => $request->input('name'),
                'prefix' => $request->input('prefix'),
                'postfix' => $request->input('postfix'),
            ]);

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Counter created successfully!',
                'data'    => $counter
            ], 201); // 201 status for resource created

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Counter creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating the Counter.',
            ], 500);
        }
    }

    // Read (Get all Counters or a single Counter by ID)
    public function fetch(Request $request, $id = null)
    {
        try {
            if ($id) {
                // Fetch a specific Counter by ID
                $counter = CounterModel::find($id);

                if (!$counter) {
                    return response()->json([
                        'code'    => 404,
                        'status'  => false,
                        'message' => 'Counter not found!'
                    ], 404); // 404 if not found
                }

                return response()->json([
                    'code'    => 200,
                    'status'  => true,
                    'message' => 'Counter retrieved successfully!',
                    'data'    => $counter,
                ], 200);
            }

            // Fetch all Counters
            $counters = CounterModel::all();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Counters retrieved successfully!',
                'data'    => $counters,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Counter retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while retrieving Counters.',
            ], 500);
        }
    }

    // Update a Counter
    public function edit(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:t_counter,name,' . $id,  // Validate the name to be unique
                'prefix' => 'nullable|string|max:32',
                'postfix' => 'nullable|string|max:32',
            ]);

            $counter = CounterModel::find($id);

            if (!$counter) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Counter not found!'
                ], 404);
            }

            // Update Counter
            $counter->update([
                'name' => $request->input('name'),
                'prefix' => $request->input('prefix'),
                'postfix' => $request->input('postfix'),
            ]);

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Counter updated successfully!',
                'data'    => $counter,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Counter update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while updating the Counter.',
            ], 500);
        }
    }

    // Delete a Counter
    public function delete($id)
    {
        try {
            $counter = CounterModel::find($id);

            if (!$counter) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Counter not found!'
                ], 404);
            }

            // Delete the Counter
            $counter->delete();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Counter deleted successfully!',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Counter deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while deleting the Counter.',
            ], 500);
        }
    }
}
