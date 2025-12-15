<?php

namespace App\Http\Controllers;
use App\Models\SupplierModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

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

}
