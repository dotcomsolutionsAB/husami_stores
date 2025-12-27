<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use App\Models\PickUpSlipModel;
use App\Models\PickUpSlipProductModel;
use App\Models\ProductStockModel;
use App\Models\ProductModel;   // your t_products model
use App\Models\GodownModel;    // your t_godown model
use App\Models\ClientModel;
use App\Models\CounterModel;

class PickUpSlipController extends Controller
{
    //
    use ApiResponse;

    // ✅ CREATE (Slip + Products)
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client'          => ['required', 'integer', 'exists:t_clients,id'], // adjust if your client table is users
                'pick_up_slip_no' => ['required', 'string', 'max:255', 'unique:t_pick_up_slip,pick_up_slip_no'],

                // allow multiple products
                'products'                        => ['required', 'array', 'min:1'],
                'products.*.product_stock_id'      => ['required', 'integer', 'exists:t_product_stocks,id'],
                'products.*.sku'                   => ['required', 'string', 'exists:t_products,sku'],
                'products.*.godown'                => ['nullable', 'integer', 'exists:t_godown,id'],
                'products.*.ctn'                   => ['required', 'integer', 'min:0'],
                'products.*.quantity'              => ['required', 'integer', 'min:0'],
                'products.*.approved'              => ['nullable', 'integer', 'min:0'],
                'products.*.remarks'               => ['nullable', 'string'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $v = $validator->validated();

            $slip = DB::transaction(function () use ($v) {

                // ✅ Lock counter row (prevents race conditions)
                $counter = CounterModel::where('name', 'pick_up_slip')
                    ->lockForUpdate()
                    ->first();

                if (!$counter) {
                    throw new \Exception("Counter 'pick_up_slip' not found.");
                }

                $expectedSlipNo = $counter->formatted; // prefix + padded number + postfix
                if (trim((string)$v['pick_up_slip_no']) !== $expectedSlipNo) {
                    throw new \Exception("Invalid pick_up_slip_no. Expected: {$expectedSlipNo}");
                }

                $slip = PickUpSlipModel::create([
                    'client'          => $v['client'],
                    'pick_up_slip_no' => $v['pick_up_slip_no'],
                    'status'          => "pending",
                ]);

                foreach ($v['products'] as $p) {
                    PickUpSlipProductModel::create([
                        'pick_up_slip_id'  => $slip->id,
                        'product_stock_id' => $p['product_stock_id'],
                        'sku'              => $p['sku'],
                        'godown'           => $p['godown'] ?? null,
                        'ctn'              => $p['ctn'],
                        'quantity'         => $p['quantity'],
                        'approved'         => $p['approved'] ?? 0,
                        'remarks'          => $p['remarks'] ?? null,
                    ]);
                }

                // ✅ Increment counter AFTER slip created successfully
                $counter->number = (int)$counter->number + 1;
                $counter->save();

                return $slip;
            });

            $slip = PickUpSlipModel::with(['clientRef', 'products'])->find($slip->id);

            return $this->success('Pick up slip created successfully.', $slip, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up slip create failed');
        }
    }

    // ✅ FETCH (Single / List with filters + pagination)
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $slip = PickUpSlipModel::with(['clientRef', 'products'])->find($id);
                if (!$slip) return $this->error('Pick up slip not found.', 404);

                return $this->success('Data fetched successfully', $slip, 200);
            }

            // LIST
            $validator = Validator::make($request->all(), [
                'limit'  => ['nullable', 'integer', 'min:1', 'max:200'],
                'offset' => ['nullable', 'integer', 'min:0'],

                'grade'  => ['nullable', 'string', 'max:255'],
                'brand'  => ['nullable', 'string', 'max:255'],
                'item'   => ['nullable', 'string', 'max:255'],
                'size'   => ['nullable', 'string', 'max:255'],
                'search' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) return $this->validation($validator);

            $limit  = max(1, (int)$request->input('limit', 10));
            $offset = max(0, (int)$request->input('offset', 0));

            $grade  = trim((string)$request->input('grade', ''));
            $brand  = trim((string)$request->input('brand', ''));
            $item   = trim((string)$request->input('item', ''));
            $size   = trim((string)$request->input('size', ''));
            $search = trim((string)$request->input('search', ''));

            $q = PickUpSlipModel::query()
                ->with(['clientRef', 'products'])
                ->orderBy('id', 'desc');

            // search by slip no or client name
            if ($search !== '') {
                $tokens = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                $q->where(function ($qq) use ($tokens) {
                    foreach ($tokens as $tok) {
                        $qq->where(function ($w) use ($tok) {
                            $w->orWhere('pick_up_slip_no', 'like', "%{$tok}%")
                              ->orWhereHas('clientRef', function ($c) use ($tok) {
                                  $c->where('name', 'like', "%{$tok}%");
                              });
                        });
                    }
                });
            }

            // product-based filters (via sku in child table -> t_products)
            if ($grade !== '' || $brand !== '' || $item !== '' || $size !== '') {
                $q->whereHas('products', function ($pp) use ($grade, $brand, $item, $size) {
                    $pp->whereExists(function ($sub) use ($grade, $brand, $item, $size) {
                        $sub->select(DB::raw(1))
                            ->from('t_products as p')
                            ->whereColumn('p.sku', 't_pick_up_slip_products.sku');

                        if ($grade !== '') $sub->where('p.grade_no', 'like', "%{$grade}%");
                        if ($brand !== '') $sub->where('p.brand', 'like', "%{$brand}%");
                        if ($item !== '')  $sub->where('p.item_name', 'like', "%{$item}%");
                        if ($size !== '')  $sub->where('p.size', 'like', "%{$size}%");
                    });
                });
            }

            $total = (clone $q)->count();

            $items = $q->skip($offset)->take($limit)->get();
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
            return $this->serverError($e, 'Pick up slip fetch failed');
        }
    }

    // ✅ EDIT (update slip + replace products)
    // public function edit(Request $request, $id)
    // {
    //     try {
    //         $slip = PickUpSlipModel::with('products')->find($id);
    //         if (!$slip) return $this->error('Pick up slip not found.', 404);

    //         $validator = Validator::make($request->all(), [
    //             'client'          => ['required', 'integer', 'exists:t_clients,id'],
    //             'pick_up_slip_no' => ['required', 'string', 'max:255', 'unique:t_pick_up_slip,pick_up_slip_no,' . $id],
    //             'status'          => ['required|in:pending,completed'],

    //             'products'                        => ['required', 'array', 'min:1'],
    //             'products.*.product_stock_id'      => ['required', 'integer', 'exists:t_product_stocks,id'],
    //             'products.*.sku'                   => ['required', 'string', 'exists:t_products,sku'],
    //             'products.*.godown'                => ['nullable', 'integer', 'exists:t_godown,id'],
    //             'products.*.ctn'                   => ['required', 'integer', 'min:0'],
    //             'products.*.quantity'              => ['required', 'integer', 'min:0'],
    //             'products.*.approved'              => ['nullable', 'integer', 'min:0'],
    //             'products.*.remarks'               => ['nullable', 'string'],
    //         ]);

    //         if ($validator->fails()) return $this->validation($validator);

    //         $v = $validator->validated();

    //         DB::transaction(function () use ($slip, $v) {

    //             $slip->update([
    //                 'client'          => $v['client'],
    //                 'pick_up_slip_no' => $v['pick_up_slip_no'],
    //                 'status'          => $v['status'],
    //             ]);

    //             // simplest: delete children and recreate
    //             PickUpSlipProductModel::where('pick_up_slip_id', $slip->id)->delete();

    //             foreach ($v['products'] as $p) {
    //                 PickUpSlipProductModel::create([
    //                     'pick_up_slip_id'  => $slip->id,
    //                     'product_stock_id' => $p['product_stock_id'],
    //                     'sku'              => $p['sku'],
    //                     'godown'           => $p['godown'] ?? null,
    //                     'ctn'              => $p['ctn'],
    //                     'quantity'         => $p['quantity'],
    //                     'approved'         => $p['approved'] ?? 0,
    //                     'remarks'          => $p['remarks'] ?? null,
    //                 ]);
    //             }
    //         });

    //         $slip = PickUpSlipModel::with(['clientRef','products'])->find($slip->id);

    //         return $this->success('Pick up slip updated successfully.', $slip, 200);

    //     } catch (\Throwable $e) {
    //         return $this->serverError($e, 'Pick up slip update failed');
    //     }
    // }

    public function edit(Request $request, $id)
    {
        try {
            $slip = PickUpSlipModel::with('products')->find($id);
            if (!$slip) return $this->error('Pick up slip not found.', 404);

            $validator = Validator::make($request->all(), [
                'client'          => ['required', 'integer', 'exists:t_clients,id'],
                'pick_up_slip_no' => ['required', 'string', 'max:255', 'unique:t_pick_up_slip,pick_up_slip_no,' . $id],
                'status'          => ['required', 'in:pending,completed'],

                'products'                        => ['required', 'array', 'min:1'],
                'products.*.product_stock_id'      => ['required', 'integer', 'exists:t_product_stocks,id'],
                'products.*.sku'                   => ['required', 'string', 'exists:t_products,sku'],
                'products.*.godown'                => ['nullable', 'integer'], // you said no need strict checks
                'products.*.ctn'                   => ['required', 'integer', 'min:0'],
                'products.*.quantity'              => ['required', 'integer', 'min:1'],
                'products.*.approved'              => ['nullable', 'integer', 'min:0'],
                'products.*.remarks'               => ['nullable', 'string'],
            ]);

            if ($validator->fails()) return $this->validation($validator);

            $v = $validator->validated();

            DB::transaction(function () use ($slip, $v) {

                // ✅ keep old status for transition check
                $oldStatus = (string)($slip->status ?? 'pending');

                // ✅ update parent
                $slip->update([
                    'client'          => $v['client'],
                    'pick_up_slip_no' => $v['pick_up_slip_no'],
                    'status'          => $v['status'],
                ]);

                // ✅ simplest: delete and recreate children
                PickUpSlipProductModel::where('pick_up_slip_id', $slip->id)->delete();

                foreach ($v['products'] as $p) {
                    PickUpSlipProductModel::create([
                        'pick_up_slip_id'  => $slip->id,
                        'product_stock_id' => (int)$p['product_stock_id'],
                        'sku'              => $p['sku'],
                        'godown'           => $p['godown'] ?? null,
                        'ctn'              => (int)$p['ctn'],
                        'quantity'         => (int)$p['quantity'],
                        'approved'         => (int)($p['approved'] ?? 0),
                        'remarks'          => $p['remarks'] ?? null,
                    ]);
                }

                // ✅ apply stock split ONLY when changing to completed
                if ($oldStatus !== 'completed' && $v['status'] === 'completed') {

                    foreach ($v['products'] as $p) {

                        $unitsToSend = (int)$p['quantity'];
                        if ($unitsToSend <= 0) continue;

                        // 🔒 lock stock row
                        $stock = ProductStockModel::where('id', (int)$p['product_stock_id'])
                            ->lockForUpdate()
                            ->first();

                        if (!$stock) {
                            throw new \Exception('Stock not found for product_stock_id: ' . (int)$p['product_stock_id']);
                        }

                        $perCtnQty = (int)($stock->quantity ?? 0); // e.g. 50
                        $stockCtn  = (int)($stock->ctn ?? 0);

                        if ($perCtnQty <= 0 || $stockCtn <= 0) {
                            throw new \Exception('Invalid stock carton setup.');
                        }

                        // ✅ TOTAL units available in this stock row (including sent)
                        $sent = 0;
                        $sent = (int)($stock->sent ?? 0);

                        $totalUnits = ($stockCtn * $perCtnQty) - $sent;

                        if ($unitsToSend > $totalUnits) {
                            throw new \Exception('Sorry, Insuffiecient Quantity');
                        }

                        // ✅ how many full cartons + remainder
                        $fullCartons = intdiv($unitsToSend, $perCtnQty);
                        $remainder   = $unitsToSend % $perCtnQty;

                        // cartons to reduce from original stock:
                        // - remove full cartons
                        // - if remainder exists, remove one more carton (we'll create it as partial row)
                        $ctnToRemove = $fullCartons + ($remainder > 0 ? 1 : 0);

                        if ($ctnToRemove > $stockCtn) {
                            throw new \Exception('Sorry, Insuffiecient Quantity');
                        }

                        // ✅ reduce cartons in original row
                        $stock->ctn = $stockCtn - $ctnToRemove;

                        // keep original sent as-is (don’t force to 50)
                        // if you want to reset sent when cartons reduce, you can do it here.
                        $stock->save();

                        // ✅ create a new partial carton stock row if remainder exists
                        if ($remainder > 0) {
                            $new = $stock->replicate();   // copy all columns
                            $new->ctn = 1;
                            $new->quantity = $perCtnQty;

                            $new->sent = $remainder;

                            $new->save();
                        }
                    }
                }
            });

            $slip = PickUpSlipModel::with(['clientRef', 'products'])->find($slip->id);

            return $this->success('Pick up slip updated successfully.', $slip, 200);

        } catch (\Throwable $e) {
            if ($e->getMessage() === 'Sorry, Insuffiecient Quantity') {
                return $this->error('Sorry, Insuffiecient Quantity', 422);
            }
            return $this->serverError($e, 'Pick up slip update failed');
        }
    }

    // ✅ DELETE
    public function delete($id)
    {
        try {
            $slip = PickUpSlipModel::find($id);
            if (!$slip) return $this->error('Pick up slip not found.', 404);

            DB::transaction(function () use ($slip) {
                PickUpSlipProductModel::where('pick_up_slip_id', $slip->id)->delete();
                $slip->delete();
            });

            return $this->success('Pick up slip deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Pick up slip delete failed');
        }
    }
}
