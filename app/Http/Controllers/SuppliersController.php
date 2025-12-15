<?php

namespace App\Http\Controllers;
use App\Models\SupplierModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class SuppliersController extends Controller
{
    //
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
}
