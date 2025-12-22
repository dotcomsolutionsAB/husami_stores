<?php

namespace App\Http\Controllers;
use App\Traits\ApiResponse;
use App\Models\ProductModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ProductsController extends Controller
{
    use ApiResponse;
    // create
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validate
            $validator = Validator::make($request->all(), [
                'sku'             => ['required','string','max:255','unique:t_products,sku'],
                'grade_no'        => ['nullable', 'string', 'max:255'],
                'item_name'       => ['required', 'string', 'max:255'],
                'size'            => ['nullable', 'string', 'max:255'],
                'brand'           => ['nullable', 'integer', 'exists:t_brand,id'],
                'units'           => ['nullable', 'integer'],
                'list_price'      => ['required', 'numeric', 'min:0'],
                'hsn'             => ['nullable', 'string', 'max:32'],
                'tax'             => ['required', 'numeric', 'min:0', 'max:100'],
                'low_stock_level' => ['nullable', 'integer', 'min:0'],
                'finish_type'     => ['nullable', 'string', 'max:255'],
                'specifications'  => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator); // ✅ only first error
            }

            // 2️⃣ Create
            $product = ProductModel::create($validator->validated());

            return $this->success('Product created successfully.', $product, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product create failed');
        }
    }

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE PRODUCT
            if ($id !== null) {
                $product = ProductModel::find($id);

                if (!$product) {
                    return $this->error('Product not found.', 404);
                }

                return $this->success('Data fetched successfully', $product, 200);
            }

            // 🔹 LIST PRODUCTS (same pattern as UserController)
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $item  = trim((string) $request->input('item', ''));
            $brand = trim((string) $request->input('brand', ''));

            $q = ProductModel::orderBy('id', 'desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('sku', 'like', "%{$search}%")
                    ->orWhere('grade_no', 'like', "%{$search}%")
                    ->orWhere('size', 'like', "%{$search}%");
                });
            }

            if ($item !== '') {
                $q->where('item_name', 'like', "%{$item}%");
            }

            if ($brand !== '') {
                $q->where('brand', (int) $brand);
            }

            $total = (clone $q)->count();

            $products = $q->skip($offset)->take($limit)->get();
            $count = $products->count();

            return $this->success('Data fetched successfully', $products, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $count,
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product fetch failed');
        }
    }

    // edit
    public function edit(Request $request, $id)
    {
        try {
            $product = ProductModel::find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'sku'             => ['required', 'string', 'max:255', 'unique:t_products,sku,' . $id],
                'grade_no'        => ['nullable', 'string', 'max:255'],
                'item_name'       => ['required', 'string', 'max:255'],
                'size'            => ['nullable', 'string', 'max:255'],
                'brand'           => ['nullable', 'integer', 'exists:t_brand,id'],
                'units'           => ['nullable', 'integer'],
                'list_price'      => ['required', 'numeric', 'min:0'],
                'hsn'             => ['nullable', 'string', 'max:32'],
                'tax'             => ['required', 'numeric', 'min:0', 'max:100'],
                'low_stock_level' => ['nullable', 'integer', 'min:0'],
                'finish_type'     => ['nullable', 'string', 'max:255'],
                'specifications'  => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $product->update($validator->validated());

            return $this->success('Product updated successfully.', $product->fresh(), 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product update failed');
        }
    }

    // delete
    public function delete($id)
    {
        try {
            $product = ProductModel::find($id);

            if (!$product) {
                return $this->error('Product not found.', 404);
            }

            $product->delete();

            return $this->success('Product deleted successfully', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product delete failed');
        }
    }
}
