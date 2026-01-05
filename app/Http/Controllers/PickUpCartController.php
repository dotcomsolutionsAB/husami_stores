<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\PickUpCartModel;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductStockModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PickUpCartController extends Controller
{
    //
    use ApiResponse;

    // CREATE
    // public function create(Request $request)
    // {
    //     try {

    //         $auth = auth('sanctum')->user();
    //         if (!$auth) {
    //             return $this->error('Authentication required.', 401);
    //         }

    //         $validator = Validator::make($request->all(), [
    //             'godown'           => ['required', 'integer', 'exists:t_godown,id'],
    //             'ctn'              => ['required', 'integer', 'min:0'],
    //             'sku'              => ['required', 'string', 'exists:t_products,sku'],
    //             'product_stock_id' => ['required', 'integer', 'exists:t_product_stocks,id'],
    //             'quantity'         => ['required', 'integer', 'min:0'],
    //         ]);

    //         if ($validator->fails()) {
    //             return $this->validation($validator);
    //         }

    //         $data = $validator->validated();
    //         // ✅ add user_id from logged user
    //         $data['user_id'] = $auth->id;

    //         $row = DB::transaction(function () use ($data) 
    //         {

    //             // Lock stock row (prevents race condition)
    //             $stock = ProductStockModel::where('id', $data['product_stock_id'])
    //                 ->lockForUpdate()
    //                 ->first();

    //             if (!$stock) {
    //                 throw new \Exception('Stock not found.');
    //             }

    //             // ✅ Total stock units = (ctn × quantity) from t_product_stocks
    //             $stockCtn = (int)($stock->ctn ?? 0);
    //             $stockQty = (int)($stock->quantity ?? 0);
    //             $totalUnits = $stockCtn * $stockQty;

    //             // ✅ sent from t_product_stocks (if column exists)
    //             $sent = 0;
    //             if (Schema::hasColumn('t_product_stocks', 'sent')) {
    //                 $sent = (int)($stock->sent ?? 0);
    //             }

    //             // ✅ sum qty already in cart for this product_stock_id
    //             $cartQty = (int) PickUpCartModel::where('product_stock_id', $stock->id)
    //                 ->sum('quantity');

    //             // ✅ sum qty in pickup slips with status = pending
    //             // If t_pick_up_slip has status column -> use it
    //             $pendingSlipQuery = DB::table('t_pick_up_slip_products as sp')
    //                 ->where('sp.product_stock_id', $stock->id);

    //             if (Schema::hasColumn('t_pick_up_slip', 'status')) {
    //                 $pendingSlipQuery
    //                     ->join('t_pick_up_slip as s', 's.id', '=', 'sp.pick_up_slip_id')
    //                     ->where('s.status', 'pending');
    //             } else {
    //                 // fallback: treat approved=0 as pending (if approved exists)
    //                 if (Schema::hasColumn('t_pick_up_slip_products', 'approved')) {
    //                     $pendingSlipQuery->where('sp.approved', 0);
    //                 }
    //             }

    //             $pendingSlipQty = (int) $pendingSlipQuery->sum('sp.quantity');

    //             // ✅ Available Qty
    //             $availableQty = $totalUnits - $cartQty - $pendingSlipQty - $sent;

    //             if ($availableQty < (int)$data['quantity']) {
    //                 throw new \Exception('Sorry, Insuffiecient Quantity');
    //             }

    //             return PickUpCartModel::create($data);
    //         });

    //         return $this->success(
    //             'Pick up cart item created successfully.',
    //             $row,
    //             200
    //         );

    //     } catch (\Throwable $e) {
    //         return $this->serverError($e, 'Pick up cart create failed');
    //     }
    // }

    public function create(Request $request)
    {
        try {
            $auth = auth('sanctum')->user();
            if (!$auth) return $this->error('Authentication required.', 401);

            $validator = Validator::make($request->all(), [
                'sku'              => ['required', 'string', 'exists:t_products,sku'],
                'product_stock_id' => ['required', 'integer', 'exists:t_product_stocks,id'],
                'quantity'         => ['required', 'integer', 'min:1'], // units requested
            ]);

            if ($validator->fails()) return $this->validation($validator);

            $data = $validator->validated();
            $data['user_id'] = $auth->id;

            $createdRows = DB::transaction(function () use ($data) {

                $stock = ProductStockModel::where('id', $data['product_stock_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$stock) throw new \Exception('Stock not found.');

                $perCtnQty = (int)($stock->quantity ?? 0);   // e.g. 50
                $stockCtn  = (int)($stock->ctn ?? 0);        // e.g. 4

                if ($perCtnQty <= 0 || $stockCtn <= 0) {
                    throw new \Exception('Invalid stock carton/quantity setup.');
                }

                // sent units from stock (if column exists)
                $sent = 0;
                if (Schema::hasColumn('t_product_stocks', 'sent')) {
                    $sent = (int)($stock->sent ?? 0);
                }

                $totalUnits = $stockCtn * $perCtnQty;

                $cartQty = (int) PickUpCartModel::where('product_stock_id', $stock->id)
                    ->sum('quantity');

                $pendingSlipQuery = DB::table('t_pick_up_slip_products as sp')
                    ->where('sp.product_stock_id', $stock->id);

                if (Schema::hasColumn('t_pick_up_slip', 'status')) {
                    $pendingSlipQuery
                        ->join('t_pick_up_slip as s', 's.id', '=', 'sp.pick_up_slip_id')
                        ->where('s.status', 'pending');
                }

                $pendingSlipQty = (int) $pendingSlipQuery->sum('sp.quantity');

                $availableQty = $totalUnits - $cartQty - $pendingSlipQty - $sent;

                if ($availableQty < (int)$data['quantity']) {
                    throw new \Exception('Sorry, Insuffiecient Quantity');
                }

                // ✅ SPLIT into carton-wise cart rows (each row: ctn=1)
                $need = (int)$data['quantity'];

                $rows = [];
                while ($need > 0) {
                    $take = min($perCtnQty, $need);

                    $rows[] = PickUpCartModel::create([
                        'user_id'         => $data['user_id'],
                        'sku'             => $data['sku'],
                        'product_stock_id'=> $data['product_stock_id'],
                        'ctn'             => 1,          // each row represents 1 carton
                        'quantity'        => $take,       // units in this carton row
                    ]);

                    $need -= $take;
                }

                return collect($rows);
            });

            return $this->success('Pick up cart item created successfully.', $createdRows, 200);

        } catch (\Throwable $e) {
            if ($e->getMessage() === 'Sorry, Insuffiecient Quantity') {
                return $this->error('Sorry, Insuffiecient Quantity', 422);
            }
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
                $row = PickUpCartModel::with([
                    'user:id,name',
                    'productStock.godown:id,name',
                    'productStock.product.brandRef.logoRef', // brand + logo
                ])->find($id);

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
            $q = PickUpCartModel::with([
                'user:id,name',
                'productStock.godown:id,name',
                'productStock.product.brandRef.logoRef',
            ])
            ->where('user_id', $auth->id)
            ->orderBy('id', 'desc');


            $total = (clone $q)->count();
            $rows = $q->skip($offset)->take($limit)->get();

            $items = $rows->map(function ($row) {

            $stock   = $row->productStock;         // t_product_stocks
            $product = $stock?->product;           // t_products
            $brand   = $product?->brandRef;        // t_brand (full object)
            $godown  = $stock?->godown;

            // Use cart qty/ctn (recommended) OR stock qty/ctn — see note below.
            $qty = (int) ($row->quantity ?? 0);
            $ctn = (int) ($row->ctn ?? 0);

            return [
                // existing keys (keep)
                'id'               => $row->id,
                'user_id'          => $row->user_id,
                'user_name'        => $row->user->name ?? '',
                'product_stock_id' => $row->product_stock_id,
                'created_at'       => $row->created_at,
                'updated_at'       => $row->updated_at,

                // ✅ NEW keys you asked (with table refs)
                'sku'       => (string) ($stock->sku ?? $row->sku ?? ''),        // t_product_stocks
                'gr'        => (string) ($product->grade_no ?? ''),              // t_products
                'item'      => (string) ($product->item_name ?? ''),             // t_products
                'size'      => (string) ($product->size ?? ''),                  // t_products
                'brand'     => $brand ?: null,                                   // t_products -> brandRef full object

                'godown'    => $godown ? ['id' => (string)$godown->id, 'name' => (string)$godown->name] : null, // t_product_stocks
                'qty'       => $qty,                                             // (cart qty recommended)
                'ctn'       => $ctn,                                             // (cart ctn recommended)
                'total_qty' => $ctn * $qty,                                      // ctn * qty
                'rack_no'   => (string) ($stock->rack_no ?? ''),                 // t_product_stocks
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
                'ctn'              => ['required', 'integer', 'min:0'],
                'sku'              => ['required', 'string', 'exists:t_products,sku'],
                'product_stock_id' => ['required', 'integer', 'exists:t_product_stocks,id'],
                'quantity'         => ['required', 'integer', 'min:0'],
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

    // merged-sku
    public function fetchMergedBySku(Request $request)
    {
        try {
            $auth = auth('sanctum')->user();
            if (!$auth) {
                return $this->error('Authentication required.', 401);
            }

            $validator = Validator::make($request->all(), [
                'limit'  => ['nullable', 'integer', 'min:1', 'max:200'],
                'offset' => ['nullable', 'integer', 'min:0'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $limit  = (int) ($request->input('limit', 10));
            $offset = (int) ($request->input('offset', 0));

            // ✅ get full cart rows for this user with needed relations
            $rows = PickUpCartModel::with([
                'productStock.product.brandRef.logoRef',
            ])
            // ->where('user_id', $auth->id)
            ->orderBy('id', 'desc')
            ->get();

            // ✅ group by SKU and merge
            $merged = $rows
                ->groupBy(function ($row) {
                    return $row->productStock?->sku ?? $row->sku ?? '';
                })
                ->map(function ($group, $sku) {

                    $first   = $group->first();
                    $stock   = $first->productStock;
                    $product = $stock?->product;
                    $brand   = $product?->brandRef;

                    $totalQty = $group->sum(function ($r) {

                        $ctnRaw = (string)($r->ctn ?? '0');
                        $qtyRaw = (string)($r->quantity ?? '0');

                        // remove commas, spaces, and any non-numeric chars (keeps digits only)
                        $ctn = (int) preg_replace('/[^0-9]/', '', $ctnRaw);
                        $qty = (int) preg_replace('/[^0-9]/', '', $qtyRaw);

                        return $ctn * $qty;
                    });

                    return [
                        'sku'       => (string) ($sku ?? ''),
                        'gr'        => (string) ($product->grade_no ?? ''),
                        'item'      => (string) ($product->item_name ?? ''),
                        'size'      => (string) ($product->size ?? ''),
                        'brand'     => $brand ?: null,
                        'total_qty' => (int) $totalQty,
                    ];
                })
                ->values();

            // ✅ pagination after merge
            $total = $merged->count();
            $paged = $merged->slice($offset, $limit)->values();
            $count = $paged->count();

            return $this->success('Data fetched successfully', $paged, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $count,
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up cart merged fetch failed');
        }
    }
}
