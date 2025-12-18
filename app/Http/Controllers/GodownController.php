<?php

namespace App\Http\Controllers;
use App\Models\GodownModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class GodownController extends Controller
{
    //
    // Create a new Godown
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation
            $request->validate([
                'name' => 'required|string|max:255',  // Validate the input
            ]);

            // 2️⃣ Create the Godown entry
            $godown = GodownModel::create([
                'name' => $request->input('name'),
            ]);

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Godown created successfully!',
                'data'    => $godown
            ], 201); // 201 status for resource created

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Godown creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating Godown.',
            ], 500);
        }
    }

    // Read (Get all Godowns or a single Godown by ID)
    public function fetch(Request $request, $id = null)
    {
        try {
            if ($id) {
                // Fetch specific Godown by ID
                $godown = GodownModel::find($id);

                if (!$godown) {
                    return response()->json([
                        'code'    => 404,
                        'status'  => false,
                        'message' => 'Godown not found!',
                    ], 404);
                }

                return response()->json([
                    'code'    => 200,
                    'status'  => true,
                    'message' => 'Godown retrieved successfully!',
                    'data'    => $godown,
                ], 200);
            }

            // Fetch all Godowns
            $godowns = GodownModel::all();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Godowns retrieved successfully!',
                'data'    => $godowns,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Godown retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while retrieving Godowns.',
            ], 500);
        }
    }

    // Update a Godown
    public function edit(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $godown = GodownModel::find($id);

            if (!$godown) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Godown not found!',
                ], 404);
            }

            // Update Godown
            $godown->update([
                'name' => $request->input('name'),
            ]);

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Godown updated successfully!',
                'data'    => $godown,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Godown update failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while updating Godown.',
            ], 500);
        }
    }

    // Delete a Godown
    public function delete($id)
    {
        try {
            $godown = GodownModel::find($id);

            if (!$godown) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Godown not found!',
                ], 404);
            }

            // Delete Godown
            $godown->delete();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Godown deleted successfully!',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Godown deletion failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while deleting Godown.',
            ], 500);
        }
    }
}
