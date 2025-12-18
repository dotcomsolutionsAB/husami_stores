<?php

namespace App\Http\Controllers;
use App\Models\ClientModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ClientsController extends Controller
{
    // create
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation (schema aligned)
            $request->validate([
                'name'            => ['required', 'string', 'max:255'],

                'address_line_1'  => ['nullable', 'string', 'max:255'],
                'address_line_2'  => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],

                'pincode'         => ['nullable', 'integer', 'digits_between:4,8'],
                'gstin'           => ['nullable', 'string', 'max:20'],

                // FK → t_state.id
                'state'           => ['nullable', 'integer', 'exists:t_state,id'],

                'country'         => ['nullable', 'string', 'max:64'],
                'mobile'          => ['nullable', 'string', 'max:32'],
                'email'           => ['nullable', 'email', 'max:255'],
            ]);

            // 2️⃣ Composite uniqueness check (name + mobile + gstin)
            $exists = ClientModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->exists();

            if ($exists) {
                return response()->json([
                    'code'    => 409,
                    'status'  => false,
                    'message' => 'Client with same Name, Mobile and GSTIN already exists.',
                ], 409);
            }

            // 3️⃣ Create client
            $client = ClientModel::create($request->only([
                'name',
                'address_line_1',
                'address_line_2',
                'city',
                'pincode',
                'gstin',
                'state',
                'country',
                'mobile',
                'email',
            ]));

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Client created successfully.',
                'data'    => $client,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Client create failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating client.',
            ], 500);
        }
    }

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE CLIENT
            if ($id !== null) {
                $client = ClientModel::find($id);

                if (! $client) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Client not found.',
                    ], 404);
                }

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'data' => $client,
                ], 200);
            }

            // 🔹 LIST CLIENTS
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $total = ClientModel::count();

            $q = ClientModel::orderBy('id','desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            $items = $q->skip($offset)->take($limit)->get();

            return response()->json([
                'code' => 200,
                'status' => true,
                'total' => $total,
                'count' => $items->count(),
                'data' => $items,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Client fetch failed', ['error'=>$e->getMessage()]);
            return response()->json(['message'=>'Failed to fetch clients'], 500);
        }
    }

    // Update (Edit) a Client
    public function edit(Request $request, $id)
    {
        try {
            // 1️⃣ Validation
            $request->validate([
                'name'            => ['required', 'string', 'max:255'],
                'address_line_1'  => ['nullable', 'string', 'max:255'],
                'address_line_2'  => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],
                'pincode'         => ['nullable', 'integer', 'digits_between:4,8'],
                'gstin'           => ['nullable', 'string', 'max:20'],
                'state'           => ['nullable', 'integer', 'exists:t_state,id'],
                'country'         => ['nullable', 'string', 'max:64'],
                'mobile'          => ['nullable', 'string', 'max:32'],
                'email'           => ['nullable', 'email', 'max:255'],
            ]);

            // 2️⃣ Check if client exists
            $client = ClientModel::find($id);
            if (!$client) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Client not found!',
                ], 404);
            }

            // 3️⃣ Check for composite uniqueness (name + mobile + gstin)
            $exists = ClientModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->where('id', '!=', $id)  // Exclude current client from the uniqueness check
                ->exists();

            if ($exists) {
                return response()->json([
                    'code'    => 409,
                    'status'  => false,
                    'message' => 'Client with same Name, Mobile and GSTIN already exists.',
                ], 409);
            }

            // 4️⃣ Update the client data
            $client->update($request->only([
                'name',
                'address_line_1',
                'address_line_2',
                'city',
                'pincode',
                'gstin',
                'state',
                'country',
                'mobile',
                'email',
            ]));

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Client updated successfully!',
                'data'    => $client,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Client update failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while updating client.',
            ], 500);
        }
    }

    // Delete a Client
    public function delete($id)
    {
        try {
            // 1️⃣ Check if client exists
            $client = ClientModel::find($id);
            if (!$client) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Client not found!',
                ], 404);
            }

            // 2️⃣ Delete the client
            $client->delete();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Client deleted successfully!',
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Client deletion failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while deleting client.',
            ], 500);
        }
    }

    // Export
    public function exportExcel(Request $request)
    {
        try {
            // 🔹 Filter Logic (same as fetch)
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $q = ClientModel::orderBy('id', 'desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            // 🔹 Fetch filtered data (no pagination)
            $clients = $q->get(); // Fetch all filtered clients without pagination

            if ($clients->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No clients found.',
                ], 404);
            }

            // 🔹 Create CSV File
            $fileName = 'clients_' . now()->format('YmdHis') . '.csv';
            $filePath = storage_path('app/public/exports/' . $fileName);

            // Open the file for writing
            $file = fopen($filePath, 'w');

            // Add headers to the CSV file
            fputcsv($file, ['ID', 'Name', 'Address Line 1', 'Address Line 2', 'City', 'Pincode', 'GSTIN', 'State', 'Country', 'Mobile', 'Email', 'Created At', 'Updated At']);

            // Write each client to the CSV
            foreach ($clients as $client) {
                fputcsv($file, [
                    $client->id,
                    $client->name,
                    $client->address_line_1,
                    $client->address_line_2,
                    $client->city,
                    $client->pincode,
                    $client->gstin,
                    $client->state,
                    $client->country,
                    $client->mobile,
                    $client->email,
                    $client->created_at,
                    $client->updated_at,
                ]);
            }

            // Close the file after writing
            fclose($file);

            // 🔹 Return link to the generated CSV file
            $fileUrl = asset('storage/exports/' . $fileName);

            return response()->json([
                'status' => true,
                'message' => 'CSV exported successfully!',
                'file_url' => $fileUrl,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Client export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to export clients.',
            ], 500);
        }
    }
}