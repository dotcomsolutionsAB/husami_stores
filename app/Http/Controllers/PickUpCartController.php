<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\PickUpCartModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PickUpCartController extends Controller
{
    //
    use ApiResponse;

    // CREATE
    public function create(Request $request)
    {
        try {

            $auth = auth('sanctum')->user();
            if (!$auth) {
                return $this->error('Authentication required.', 401);
            }

            $validator = Validator::make($request->all(), [
                'godown'           => ['required', 'integer', 'exists:t_godown,id'],
                'ctn'              => ['required', 'integer', 'min:0'],
                'sku'              => ['required', 'string', 'exists:t_products,sku'],
                'product_stock_id' => ['required', 'integer', 'exists:t_product_stocks,id'],
                'total_quantity'   => ['required', 'numeric', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $data = $validator->validated();
            // ✅ add user_id from logged user
            $data['user_id'] = $auth->id;

            $row = PickUpCartModel::create($data);

            return $this->success(
                'Pick up cart item created successfully.',
                $row,
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up cart create failed');
        }
    }

    // FETCH (single OR list with limit/offset)
    public function fetch(Request $request, $id = null)
    {
        try {
            $auth = auth('sanctum')->user();
            if (!$auth) {
                return $this->error('Authentication required.', 401);
            }

            // SINGLE
            if ($id !== null) {
                $row = PickUpCartModel::find($id);

                if (!$row) {
                    return $this->error('Record not found.', 404);
                }

                return $this->success('Data fetched successfully', $row, 200);
            }

            // LIST + pagination (limit/offset only)
            $validator = Validator::make($request->all(), [
                'limit'  => ['nullable', 'integer', 'min:1', 'max:200'],
                'offset' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $limit  = (int) ($request->input('limit', 10));
            $offset = (int) ($request->input('offset', 0));

            // ✅ fetch only logged user's cart (recommended)
            $q = PickUpCartModel::with('user:id,name')
                ->where('user_id', $auth->id)
                ->orderBy('id', 'desc');


            $total = (clone $q)->count();
            $rows = $q->skip($offset)->take($limit)->get();

            $items = $q->skip($offset)->take($limit)->get()->map(function ($row) {
                return [
                    'id'             => $row->id,
                    'user_id'        => $row->user_id,
                    'user_name'      => $row->user->name ?? '',
                    'godown'         => $row->godown,
                    'ctn'            => $row->ctn,
                    'sku'            => $row->sku,
                    'product_stock_id' => $row->product_stock_id,
                    'total_quantity' => $row->total_quantity,
                    'created_at'     => $row->created_at,
                    'updated_at'     => $row->updated_at,
                ];
            });

            $count = $items->count();

            return $this->success('Data fetched successfully', $items, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $count,
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up cart fetch failed');
        }
    }

    // EDIT
    public function edit(Request $request, $id)
    {
        try {
            $row = PickUpCartModel::find($id);

            if (!$row) {
                return $this->error('Record not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'godown'           => ['required', 'integer', 'exists:t_godown,id'],
                'ctn'              => ['required', 'integer', 'min:0'],
                'sku'              => ['required', 'string', 'exists:t_products,sku'],
                'product_stock_id' => ['required', 'integer', 'exists:t_product_stocks,id'],
                'total_quantity'   => ['required', 'numeric', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $row->update($validator->validated());

            return $this->success(
                'Pick up cart item updated successfully.',
                $row->fresh(),
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up cart update failed');
        }
    }

    // DELETE
    public function delete($id)
    {
        try {
            $row = PickUpCartModel::find($id);

            if (!$row) {
                return $this->error('Record not found.', 404);
            }

            $row->delete();

            return $this->success(
                'Pick up cart item deleted successfully.',
                [],
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up cart delete failed');
        }
    }
}
