<?php

namespace App\Http\Controllers;
use App\Models\SupplierModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SuppliersController extends Controller
{
    // create
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation
            $request->validate([
                'name'            => ['required', 'string', 'max:255'],

                'address_line_1'  => ['nullable', 'string', 'max:255'],
                'address_line_2'  => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],

                // unsignedInteger
                'pincode'         => ['nullable', 'integer', 'digits_between:4,8'],

                'gstin'           => ['nullable', 'string', 'max:20'],

                // FK → t_state.id
                'state'           => ['nullable', 'integer', 'exists:t_state,id'],

                'country'         => ['nullable', 'string', 'max:64'],
                'mobile'          => ['nullable', 'string', 'max:32'],
                'email'           => ['nullable', 'email', 'max:255'],
            ]);

            $exists = SupplierModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->exists();

            if ($exists) {
                return response()->json([
                    'code'    => 409,
                    'status'  => false,
                    'message' => 'Supplier with same Name, Mobile and GSTIN already exists.',
                ], 409);
            }

            // 2️⃣ Create supplier
            $supplier = SupplierModel::create($request->only([
                'name','address_line_1','address_line_2',
                'city','pincode','gstin','state',
                'country','mobile','email'
            ]));

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Supplier created successfully.',
                'data'    => $supplier,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Supplier create failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating supplier.',
            ], 500);
        }
    }

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE SUPPLIER
            if ($id !== null) {
                $supplier = SupplierModel::find($id);

                if (! $supplier) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Supplier not found.',
                    ], 404);
                }

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'data' => $supplier,
                ], 200);
            }

            // 🔹 LIST SUPPLIERS
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $total = SupplierModel::count();

            $q = SupplierModel::orderBy('id','desc');

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
            Log::error('Supplier fetch failed', ['error'=>$e->getMessage()]);
            return response()->json(['message'=>'Failed to fetch suppliers'], 500);
        }
    }

    // Update a Supplier
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

            // 2️⃣ Check if supplier exists
            $supplier = SupplierModel::find($id);

            if (!$supplier) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Supplier not found!',
                ], 404);
            }

            // 3️⃣ Check for composite uniqueness (name + mobile + gstin)
            $exists = SupplierModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->where('id', '!=', $id)  // Exclude current supplier from the uniqueness check
                ->exists();

            if ($exists) {
                return response()->json([
                    'code'    => 409,
                    'status'  => false,
                    'message' => 'Supplier with same Name, Mobile, and GSTIN already exists.',
                ], 409);
            }

            // 4️⃣ Update the supplier data
            $supplier->update($request->only([
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
                'message' => 'Supplier updated successfully!',
                'data'    => $supplier,
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Supplier update failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while updating the supplier.',
            ], 500);
        }
    }

    // Delete a Supplier
    public function delete($id)
    {
        try {
            // 1️⃣ Check if supplier exists
            $supplier = SupplierModel::find($id);

            if (!$supplier) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'Supplier not found!',
                ], 404);
            }

            // 2️⃣ Delete the supplier
            $supplier->delete();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Supplier deleted successfully!',
            ], 200);

        } catch (\Throwable $e) {
            // Log the error details
            Log::error('Supplier deletion failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while deleting the supplier.',
            ], 500);
        }
    }

    // export
    public function exportExcel(Request $request)
    {
        try {
            // 🔹 Filter Logic (same as fetch)
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $q = SupplierModel::orderBy('id', 'desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            // 🔹 Fetch filtered data (no pagination)
            $suppliers = $q->get(); // Fetch all filtered suppliers without pagination

            if ($suppliers->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No suppliers found.',
                ], 404);
            }

            // 🔹 Create CSV File
            $fileName = 'suppliers_' . now()->format('YmdHis') . '.csv';
            $filePath = storage_path('app/public/exports/suppliers/' . $fileName);

            // Open the file for writing
            $file = fopen($filePath, 'w');

            // Add headers to the CSV file
            fputcsv($file, ['ID', 'Name', 'Address Line 1', 'Address Line 2', 'City', 'Pincode', 'GSTIN', 'State', 'Country', 'Mobile', 'Email', 'Created At', 'Updated At']);

            // Write each supplier to the CSV
            foreach ($suppliers as $supplier) {
                fputcsv($file, [
                    $supplier->id,
                    $supplier->name,
                    $supplier->address_line_1,
                    $supplier->address_line_2,
                    $supplier->city,
                    $supplier->pincode,
                    $supplier->gstin,
                    $supplier->state,
                    $supplier->country,
                    $supplier->mobile,
                    $supplier->email,
                    $supplier->created_at,
                    $supplier->updated_at,
                ]);
            }

            // Close the file after writing
            fclose($file);

            // 🔹 Return link to the generated CSV file
            $fileUrl = asset('storage/exports/suppliers/' . $fileName);

            return response()->json([
                'status' => true,
                'message' => 'CSV exported successfully!',
                'file_url' => $fileUrl,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Supplier export failed', ['error' => $e->getMessage()]);
            return response()->json([
                'status' => false,
                'message' => 'Failed to export suppliers.',
            ], 500);
        }
    }
}
