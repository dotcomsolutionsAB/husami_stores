<?php

namespace App\Http\Controllers;
use App\Models\ProductModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    // create
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

    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE PRODUCT
            if ($id !== null) {
                $product = ProductModel::find($id);

                if (! $product) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Product not found.',
                    ], 404);
                }

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'data' => $product,
                ], 200);
            }

            // 🔹 LIST PRODUCTS
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $item  = $request->input('item');
            $brand = $request->input('brand');

            $total = ProductModel::count();

            $q = ProductModel::orderBy('id','desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('grade_no', 'like', "%{$search}%")
                    ->orWhere('size', 'like', "%{$search}%");
                });
            }

            if (!empty($item)) {
                $q->where('item_name', 'like', "%{$item}%");
            }

            if (!empty($brand)) {
                $q->where('brand', (int)$brand);
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
            Log::error('Product fetch failed', ['error'=>$e->getMessage()]);
            return response()->json(['message'=>'Failed to fetch products'], 500);
        }
    }

}
