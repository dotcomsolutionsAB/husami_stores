<?php

namespace App\Http\Controllers;
use App\Models\ProductModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    //
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation
            $request->validate([
                'grade_no'        => ['nullable', 'string', 'max:255'],
                'item_name'       => ['required', 'string', 'max:255'],
                'size'            => ['nullable', 'string', 'max:255'],

                // FK fields
                'brand'           => ['nullable', 'integer', 'exists:t_brand,id'],
                'units'           => ['nullable', 'integer'],

                'list_price'      => ['required', 'numeric', 'min:0'],
                'hsn'             => ['nullable', 'string', 'max:32'],
                'tax'             => ['required', 'numeric', 'min:0', 'max:100'],
                'low_stock_level' => ['nullable', 'integer', 'min:0'],
            ]);

            // 2️⃣ Create product
            $product = ProductModel::create($request->only([
                'grade_no','item_name','size','brand','units',
                'list_price','hsn','tax','low_stock_level'
            ]));

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Product created successfully.',
                'data'    => $product,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Product create failed', ['error' => $e->getMessage()]);
            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating product.',
            ], 500);
        }
    }
}
