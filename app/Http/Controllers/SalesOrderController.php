<?php

namespace App\Http\Controllers;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderProductModel;
use App\Models\CounterModel;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesOrderController extends Controller
{
    //
    use ApiResponse;

    /* ===================== CREATE ===================== */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client' => ['required','integer','exists:t_clients,id'],

                // counter formatted value will be passed from UI
                // 'sales_order_no' => ['required','string','max:255','unique:t_sales_order,sales_order_no'],

                'sales_order_date' => ['nullable','date'],

                // ✅ frontend sends quotation no string
                'quotation' => ['required','string','exists:t_quotation,quotation'],

                'client_order_no' => ['nullable','string','max:255'],

                'gross_total' => ['nullable','numeric'],
                'packing_and_forwarding' => ['nullable','numeric'],
                'freight' => ['nullable','numeric'],
                'total_tax' => ['nullable','numeric'],
                'round_off' => ['nullable','numeric'],
                'grand_total' => ['nullable','numeric'],

                // 'prices' => ['nullable','string','max:255'],
                // 'p_and_f' => ['nullable','string','max:255'],
                // 'freight' => ['nullable','string','max:255'],
                // 'delivery' => ['nullable','string','max:255'],
                // 'payment' => ['nullable','string','max:255'],
                // 'validity' => ['nullable','string','max:255'],
                // 'remarks' => ['nullable','string'],
                'terms' => 'nullable|array',

                'terms.prices'   => 'nullable|string|max:255',
                'terms.p_and_f'  => 'nullable|string|max:255',
                'terms.freight'  => 'nullable|string|max:255',
                'terms.delivery' => 'nullable|string|max:255',
                'terms.payment'  => 'nullable|string|max:255',
                'terms.validity' => 'nullable|string|max:255',
                'terms.remarks'  => 'nullable|string',

                // products
                'products' => ['required','array','min:1'],
                'products.*.sku' => ['required','string','exists:t_products,sku'],
                'products.*.qty' => ['required','integer','min:1'],
                'products.*.unit' => ['nullable','integer'],
                'products.*.price' => ['nullable','numeric'],
                'products.*.discount' => ['nullable','numeric'],
                'products.*.hsn' => ['nullable','string','max:32'],
                'products.*.tax' => ['nullable','numeric'],

                // file kept null for now
                'file' => ['nullable'],
            ]);

            if ($validator->fails()) return $this->validation($validator);
            $v = $validator->validated();

            // ✅ quotation_no -> quotation_id
            $quotationId = DB::table('t_quotation')
                ->where('quotation', $v['quotation'])
                ->value('id');

            if (!$quotationId) {
                return $this->error("Invalid quotation.", 422);
            }

            $so = DB::transaction(function () use ($v, $quotationId) {

                // ✅ Lock counter row + verify
                $counter = CounterModel::where('name', 'sales_order')
                    ->lockForUpdate()
                    ->first();

                if (!$counter) {
                    throw new \Exception("Counter 'sales_order' not found.");
                }

                $expectedNo = $counter->formatted;
                // if (trim((string)$v['sales_order_no']) !== $expectedNo) {
                //     throw new \Exception("Invalid sales_order_no. Expected: {$expectedNo}");
                // }
                $exists = SalesOrderModel::where('sales_order_no', $expectedNo)->exists();
                if ($exists) {
                    throw new \Exception("Sales order number already used: {$expectedNo}");
                }

                $terms = $v['terms'] ?? [];

                // ✅ create header
                $so = SalesOrderModel::create([
                    'client' => (int)$v['client'],
                    'sales_order_no' => $expectedNo,
                    'sales_order_date' => $v['sales_order_date'] ?? null,
                    'quotation' => (int)$quotationId,
                    'client_order_no' => $v['client_order_no'] ?? null,

                    'gross_total' => $v['gross_total'] ?? 0,
                    'packing_and_forwarding' => $v['packing_and_forwarding'] ?? 0,
                    'freight_val' => $v['freight'] ?? 0,
                    'total_tax' => $v['total_tax'] ?? 0,
                    'round_off' => $v['round_off'] ?? 0,
                    'grand_total' => $v['grand_total'] ?? 0,

                    // 'prices' => $v['prices'] ?? null,
                    // 'p_and_f' => $v['p_and_f'] ?? null,
                    // 'freight' => $v['freight'] ?? null,
                    // 'delivery' => $v['delivery'] ?? null,
                    // 'payment' => $v['payment'] ?? null,
                    // 'validity' => $v['validity'] ?? null,
                    // 'remarks' => $v['remarks'] ?? null,

                    'prices'   => $terms['prices'] ?? null,
                    'p_and_f'  => $terms['p_and_f'] ?? null,
                    'freight'  => $terms['freight'] ?? null,   // NOTE: this is the TEXT freight term, not numeric freight
                    'delivery' => $terms['delivery'] ?? null,
                    'payment'  => $terms['payment'] ?? null,
                    'validity' => $terms['validity'] ?? null,
                    'remarks'  => $terms['remarks'] ?? null,

                    'file' => null,
                ]);

                // ✅ insert products
                $ins = [];
                foreach ($v['products'] as $p) {
                    $ins[] = [
                        'sales_order' => $so->id,
                        'sku' => (string)$p['sku'],
                        'qty' => (int)$p['qty'],
                        'unit' => isset($p['unit']) ? (int)$p['unit'] : null,
                        'price' => isset($p['price']) ? (float)$p['price'] : 0,
                        'discount' => isset($p['discount']) ? (float)$p['discount'] : 0,
                        'hsn' => $p['hsn'] ?? null,
                        'tax' => isset($p['tax']) ? (float)$p['tax'] : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('t_sales_order_products')->insert($ins);

                // ✅ increment counter
                $counter->number = (int)$counter->number + 1;
                $counter->save();

                return $so;
            });

            // return fresh (single style)
            // return $this->fetch(new Request(), $so->id);
            $fresh = SalesOrderModel::with('products')->find($so->id);

            return $this->success('Sales Order created successfully.', [
                'sales_order' => $fresh,
                'file_url'    => null,
            ], 201);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales order create failed');
        }
    }

    /* ===================== FETCH (LIST + SINGLE) ===================== */
    public function fetch(Request $request, $id = null)
    {
        try {
            // -------- SINGLE (header + products) --------
            if ($id !== null) {
                $row = SalesOrderModel::query()
                    ->from('t_sales_order as so')
                    ->leftJoin('t_clients as c', 'c.id', '=', 'so.client')
                    ->leftJoin('t_quotation as qtn', 'qtn.id', '=', 'so.quotation')
                    ->select([
                        'so.id','so.sales_order_no','so.sales_order_date','so.client_order_no',
                        'so.gross_total','so.packing_and_forwarding','so.freight_val',
                        'so.total_tax','so.round_off','so.grand_total',
                        'so.prices','so.p_and_f','so.freight','so.delivery','so.payment','so.validity','so.remarks','so.file',

                        'c.id as client_id','c.name as client_name',
                        'qtn.id as quotation_id','qtn.quotation as quotation_no',
                    ])
                    ->where('so.id', $id)
                    ->first();

                if (!$row) return $this->error('Sales order not found.', 404);

                // products (include product name)
                $products = DB::table('t_sales_order_products as sop')
                    ->leftJoin('t_products as p', 'p.sku', '=', 'sop.sku')
                    ->where('sop.sales_order', $row->id)
                    ->select([
                        'sop.id','sop.sales_order','sop.sku',
                        'p.item_name as product_name',
                        'sop.qty','sop.unit','sop.price','sop.discount','sop.hsn','sop.tax'
                    ])
                    ->orderBy('sop.id','asc')
                    ->get()
                    ->map(function ($p) {
                        return [
                            'id' => (string)$p->id,
                            'sku' => (string)$p->sku,
                            'product_name' => (string)($p->product_name ?? ''),
                            'qty' => (string)$p->qty,
                            'unit' => $p->unit ? (string)$p->unit : null,
                            'price' => (string)$p->price,
                            'discount' => (string)$p->discount,
                            'hsn' => (string)($p->hsn ?? ''),
                            'tax' => (string)$p->tax,
                        ];
                    })->values();

                return $this->success('Data fetched successfully', [
                    'sales_order' => [
                        'id' => (string)$row->id,
                        'sales_order_no' => (string)$row->sales_order_no,
                        'sales_order_date' => $row->sales_order_date,
                        'client_order_no' => (string)($row->client_order_no ?? ''),

                        'client' => $row->client_id ? [
                            'id' => (string)$row->client_id,
                            'name' => (string)$row->client_name,
                        ] : null,

                        'quotation' => $row->quotation_id ? [
                            'id' => (string)$row->quotation_id,
                            'quotation' => (string)$row->quotation_no,
                        ] : null,

                        'gross_total' => (string)$row->gross_total,
                        'packing_and_forwarding' => (string)$row->packing_and_forwarding,
                        'freight_val' => (string)$row->freight_val,
                        'total_tax' => (string)$row->total_tax,
                        'round_off' => (string)$row->round_off,
                        'grand_total' => (string)$row->grand_total,

                        'prices' => $row->prices,
                        'p_and_f' => $row->p_and_f,
                        'freight' => $row->freight,
                        'delivery' => $row->delivery,
                        'payment' => $row->payment,
                        'validity' => $row->validity,
                        'remarks' => $row->remarks,
                        'file' => null,
                    ],
                    'products' => $products,
                ], 200);
            }

            // -------- LIST (header only + search) --------
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $q = SalesOrderModel::query()
                ->from('t_sales_order as so')
                ->leftJoin('t_clients as c', 'c.id', '=', 'so.client')
                ->leftJoin('t_quotation as qtn', 'qtn.id', '=', 'so.quotation')
                ->select([
                    'so.id','so.sales_order_no','so.sales_order_date','so.client_order_no',
                    'so.gross_total','so.total_tax','so.round_off','so.grand_total','so.file',
                    'c.id as client_id','c.name as client_name',
                    'qtn.id as quotation_id','qtn.quotation as quotation_no',
                ])
                ->orderBy('so.id','desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('so.sales_order_no', 'like', "%{$search}%")
                      ->orWhere('so.client_order_no', 'like', "%{$search}%")
                      ->orWhere('c.name', 'like', "%{$search}%")
                      ->orWhere('qtn.quotation', 'like', "%{$search}%")
                      ->orWhereExists(function ($sub) use ($search) {
                          $sub->select(DB::raw(1))
                              ->from('t_sales_order_products as sop')
                              ->join('t_products as p', 'p.sku', '=', 'sop.sku')
                              ->whereColumn('sop.sales_order', 'so.id')
                              ->where('p.item_name', 'like', "%{$search}%");
                      });
                });
            }

            $total = (clone $q)->count();
            $items = $q->skip($offset)->take($limit)->get();

            $data = $items->map(function ($it) {
                return [
                    'id' => (string)$it->id,
                    'sales_order_no' => (string)$it->sales_order_no,
                    'sales_order_date' => $it->sales_order_date,
                    'client_order_no' => (string)($it->client_order_no ?? ''),

                    'client' => $it->client_id ? [
                        'id' => (string)$it->client_id,
                        'name' => (string)$it->client_name,
                    ] : null,

                    'quotation' => $it->quotation_id ? [
                        'id' => (string)$it->quotation_id,
                        'quotation' => (string)$it->quotation_no,
                    ] : null,

                    'gross_total' => (string)$it->gross_total,
                    'total_tax' => (string)$it->total_tax,
                    'round_off' => (string)$it->round_off,
                    'grand_total' => (string)$it->grand_total,

                    'file' => null,
                ];
            })->values();

            return $this->success('Data fetched successfully', $data, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $data->count(),
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales order fetch failed');
        }
    }

    /* ===================== UPDATE ===================== */
    public function edit(Request $request, $id)
    {
        try {
            $so = SalesOrderModel::find($id);
            if (!$so) return $this->error('Sales order not found.', 404);

            $validator = Validator::make($request->all(), [
                'client' => ['sometimes','integer','exists:t_clients,id'],
                'sales_order_date' => ['sometimes','nullable','date'],

                // frontend passes quotation no
                'quotation' => ['sometimes','string','exists:t_quotation,quotation'],

                'client_order_no' => ['sometimes','nullable','string','max:255'],

                'gross_total' => ['sometimes','nullable','numeric'],
                'packing_and_forwarding' => ['sometimes','nullable','numeric'],
                'freight_val' => ['sometimes','nullable','numeric'],
                'total_tax' => ['sometimes','nullable','numeric'],
                'round_off' => ['sometimes','nullable','numeric'],
                'grand_total' => ['sometimes','nullable','numeric'],

                // 'prices' => ['sometimes','nullable','string','max:255'],
                // 'p_and_f' => ['sometimes','nullable','string','max:255'],
                // 'freight' => ['sometimes','nullable','string','max:255'],
                // 'delivery' => ['sometimes','nullable','string','max:255'],
                // 'payment' => ['sometimes','nullable','string','max:255'],
                // 'validity' => ['sometimes','nullable','string','max:255'],
                // 'remarks' => ['sometimes','nullable','string'],

                'terms' => ['sometimes','nullable','array'],

                'terms.prices'   => 'nullable|string|max:255',
                'terms.p_and_f'  => 'nullable|string|max:255',
                'terms.freight'  => 'nullable|string|max:255',
                'terms.delivery' => 'nullable|string|max:255',
                'terms.payment'  => 'nullable|string|max:255',
                'terms.validity' => 'nullable|string|max:255',
                'terms.remarks'  => 'nullable|string',

                'products' => ['sometimes','array','min:1'],
                'products.*.sku' => ['required_with:products','string','exists:t_products,sku'],
                'products.*.qty' => ['required_with:products','integer','min:1'],
                'products.*.unit' => ['nullable','integer'],
                'products.*.price' => ['nullable','numeric'],
                'products.*.discount' => ['nullable','numeric'],
                'products.*.hsn' => ['nullable','string','max:32'],
                'products.*.tax' => ['nullable','numeric'],
            ]);

            if ($validator->fails()) return $this->validation($validator);
            $v = $validator->validated();

            $terms = $v['terms'] ?? null;  // null if not sent

            $quotationId = null;
            if (!empty($v['quotation'])) {
                $quotationId = DB::table('t_quotation')->where('quotation', $v['quotation'])->value('id');
                if (!$quotationId) throw new \Exception("Invalid quotation not found: {$v['quotation']}");
            }

            DB::transaction(function () use ($so, $v, $quotationId, $terms) {
                $so->fill([
                    'client' => $v['client'] ?? $so->client,
                    'sales_order_date' => array_key_exists('sales_order_date',$v) ? $v['sales_order_date'] : $so->sales_order_date,
                    'quotation' => $quotationId ?? $so->quotation,
                    'client_order_no' => array_key_exists('client_order_no',$v) ? $v['client_order_no'] : $so->client_order_no,

                    'gross_total' => array_key_exists('gross_total',$v) ? ($v['gross_total'] ?? 0) : $so->gross_total,
                    'packing_and_forwarding' => array_key_exists('packing_and_forwarding',$v) ? ($v['packing_and_forwarding'] ?? 0) : $so->packing_and_forwarding,
                    'freight_val' => array_key_exists('freight_val',$v) ? ($v['freight_val'] ?? 0) : $so->freight_val,
                    'total_tax' => array_key_exists('total_tax',$v) ? ($v['total_tax'] ?? 0) : $so->total_tax,
                    'round_off' => array_key_exists('round_off',$v) ? ($v['round_off'] ?? 0) : $so->round_off,
                    'grand_total' => array_key_exists('grand_total',$v) ? ($v['grand_total'] ?? 0) : $so->grand_total,

                    // 'prices' => array_key_exists('prices',$v) ? $v['prices'] : $so->prices,
                    // 'p_and_f' => array_key_exists('p_and_f',$v) ? $v['p_and_f'] : $so->p_and_f,
                    // 'freight' => array_key_exists('freight',$v) ? $v['freight'] : $so->freight,
                    // 'delivery' => array_key_exists('delivery',$v) ? $v['delivery'] : $so->delivery,
                    // 'payment' => array_key_exists('payment',$v) ? $v['payment'] : $so->payment,
                    // 'validity' => array_key_exists('validity',$v) ? $v['validity'] : $so->validity,
                    // 'remarks' => array_key_exists('remarks',$v) ? $v['remarks'] : $so->remarks,

                    
                    'prices'   => (is_array($terms) && array_key_exists('prices', $terms))   ? $terms['prices']   : $so->prices,
                    'p_and_f'  => (is_array($terms) && array_key_exists('p_and_f', $terms))  ? $terms['p_and_f']  : $so->p_and_f,
                    'freight'  => (is_array($terms) && array_key_exists('freight', $terms))  ? $terms['freight']  : $so->freight,
                    'delivery' => (is_array($terms) && array_key_exists('delivery', $terms)) ? $terms['delivery'] : $so->delivery,
                    'payment'  => (is_array($terms) && array_key_exists('payment', $terms))  ? $terms['payment']  : $so->payment,
                    'validity' => (is_array($terms) && array_key_exists('validity', $terms)) ? $terms['validity'] : $so->validity,
                    'remarks'  => (is_array($terms) && array_key_exists('remarks', $terms))  ? $terms['remarks']  : $so->remarks,
                ]);

                $so->save();

                if (isset($v['products'])) {
                    DB::table('t_sales_order_products')->where('sales_order', $so->id)->delete();

                    $ins = [];
                    foreach ($v['products'] as $p) {
                        $ins[] = [
                            'sales_order' => $so->id,
                            'sku' => (string)$p['sku'],
                            'qty' => (int)$p['qty'],
                            'unit' => isset($p['unit']) ? (int)$p['unit'] : null,
                            'price' => isset($p['price']) ? (float)$p['price'] : 0,
                            'discount' => isset($p['discount']) ? (float)$p['discount'] : 0,
                            'hsn' => $p['hsn'] ?? null,
                            'tax' => isset($p['tax']) ? (float)$p['tax'] : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    DB::table('t_sales_order_products')->insert($ins);
                }
            });

            // return $this->fetch(new Request(), $so->id);
            $fresh = SalesOrderModel::with('products')->find($so->id);

            return $this->success('Sales Order updated successfully.', [
                'sales_order' => $fresh,
                'file_url'    => null,
            ], 201);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales order update failed');
        }
    }

    /* ===================== DELETE ===================== */
    public function delete($id)
    {
        try {
            $so = SalesOrderModel::find($id);
            if (!$so) return $this->error('Sales order not found.', 404);

            DB::transaction(function () use ($so) {
                DB::table('t_sales_order_products')->where('sales_order', $so->id)->delete();
                $so->delete();
            });

            return $this->success('Sales order deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales order delete failed');
        }
    }
}
