<?php

namespace App\Http\Controllers;
use App\Models\SalesInvoiceModel;
use App\Models\SalesInvoiceProductModel;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SalesInvoiceController extends Controller
{
    //
    use ApiResponse;

    /* ================= CREATE ================= */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client'      => ['required','integer','exists:t_clients,id'],

                // counter formatted value passed from frontend
                'invoice_no'  => ['required','string','max:255','unique:t_sales_invoice,invoice_no'],

                'invoice_date'=> ['nullable','date'],

                // frontend sends sales_order_no string
                'sales_order_no' => ['required','string','exists:t_sales_order,sales_order_no'],

                'gross_total' => ['nullable','numeric'],
                'packing_and_forwarding' => ['nullable','numeric'],
                'freight_val' => ['nullable','numeric'],
                'total_tax'   => ['nullable','numeric'],
                'round_off'   => ['nullable','numeric'],
                'grand_total' => ['nullable','numeric'],

                'prices'   => ['nullable','string','max:255'],
                'p_and_f'  => ['nullable','string','max:255'],
                'freight'  => ['nullable','string','max:255'],
                'delivery' => ['nullable','string','max:255'],
                'payment'  => ['nullable','string','max:255'],
                'validity' => ['nullable','string','max:255'],
                'remarks'  => ['nullable','string'],

                // products
                'products' => ['required','array','min:1'],
                'products.*.sku' => ['required','string','exists:t_products,sku'],
                'products.*.qty' => ['required','integer','min:1'],
                'products.*.unit' => ['nullable','integer'],
                'products.*.price' => ['nullable','numeric'],
                'products.*.discount' => ['nullable','numeric'],
                'products.*.hsn' => ['nullable','string','max:32'],
                'products.*.tax' => ['nullable','numeric'],
            ]);

            if ($validator->fails()) return $this->validation($validator);
            $v = $validator->validated();

            // sales_order_no string -> sales_order id
            $salesOrderId = DB::table('t_sales_order')
                ->where('sales_order_no', $v['sales_order_no'])
                ->value('id');

            if (!$salesOrderId) {
                throw new \Exception("Invalid sales_order_no. Not found: {$v['sales_order_no']}");
            }

            $inv = DB::transaction(function () use ($v, $salesOrderId) {

                // counter verify
                $counter = CounterModel::where('name', 'sales_invoice')
                    ->lockForUpdate()
                    ->first();

                if (!$counter) throw new \Exception("Counter 'sales_invoice' not found.");

                $expectedNo = $counter->formatted;
                if (trim((string)$v['invoice_no']) !== $expectedNo) {
                    throw new \Exception("Invalid invoice_no. Expected: {$expectedNo}");
                }

                $inv = SalesInvoiceModel::create([
                    'client' => (int)$v['client'],
                    'invoice_no' => $v['invoice_no'],
                    'invoice_date' => $v['invoice_date'] ?? null,

                    // ✅ store sales order ID in DB
                    'sales_order_no' => (int)$salesOrderId,

                    'gross_total' => $v['gross_total'] ?? 0,
                    'packing_and_forwarding' => $v['packing_and_forwarding'] ?? 0,
                    'freight_val' => $v['freight_val'] ?? 0,
                    'total_tax' => $v['total_tax'] ?? 0,
                    'round_off' => $v['round_off'] ?? 0,
                    'grand_total' => $v['grand_total'] ?? 0,

                    'prices' => $v['prices'] ?? null,
                    'p_and_f' => $v['p_and_f'] ?? null,
                    'freight' => $v['freight'] ?? null,
                    'delivery' => $v['delivery'] ?? null,
                    'payment' => $v['payment'] ?? null,
                    'validity' => $v['validity'] ?? null,
                    'remarks' => $v['remarks'] ?? null,

                    'file' => null,
                ]);

                // insert products
                $ins = [];
                foreach ($v['products'] as $p) {
                    $ins[] = [
                        'sales_invoice' => $inv->id,
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
                DB::table('t_sales_invoice_products')->insert($ins);

                // increment counter after success
                $counter->number = (int)$counter->number + 1;
                $counter->save();

                return $inv;
            });

            $fresh = SalesInvoiceModel::with('products')->find($inv->id);

            return $this->success('Sales Invoice created successfully.', [
                'sales_invoice' => $fresh,
                'file_url'      => null,
            ], 201);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales invoice create failed');
        }
    }

    /* ================= FETCH (LIST + SINGLE) ================= */
    public function fetch(Request $request, $id = null)
    {
        try {
            // -------- SINGLE (parent + child) --------
            if ($id !== null) {
                $row = SalesInvoiceModel::query()
                    ->from('t_sales_invoice as si')
                    ->leftJoin('t_clients as c', 'c.id', '=', 'si.client')
                    ->leftJoin('t_sales_order as so', 'so.id', '=', 'si.sales_order_no')
                    ->select([
                        'si.id','si.invoice_no','si.invoice_date',
                        'si.gross_total','si.packing_and_forwarding','si.freight_val',
                        'si.total_tax','si.round_off','si.grand_total',
                        'si.prices','si.p_and_f','si.freight','si.delivery','si.payment','si.validity','si.remarks','si.file',

                        'c.id as client_id','c.name as client_name',
                        'so.id as sales_order_id','so.sales_order_no as sales_order_no_str',
                    ])
                    ->where('si.id', $id)
                    ->first();

                if (!$row) return $this->error('Sales invoice not found.', 404);

                $products = DB::table('t_sales_invoice_products as sip')
                    ->leftJoin('t_products as p', 'p.sku', '=', 'sip.sku')
                    ->where('sip.sales_invoice', $row->id)
                    ->select([
                        'sip.id','sip.sku','p.item_name as product_name',
                        'sip.qty','sip.unit','sip.price','sip.discount','sip.hsn','sip.tax'
                    ])
                    ->orderBy('sip.id','asc')
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
                    'sales_invoice' => [
                        'id' => (string)$row->id,
                        'invoice_no' => (string)$row->invoice_no,
                        'invoice_date' => $row->invoice_date,

                        'client' => $row->client_id ? [
                            'id' => (string)$row->client_id,
                            'name' => (string)$row->client_name,
                        ] : null,

                        'sales_order' => $row->sales_order_id ? [
                            'id' => (string)$row->sales_order_id,
                            'sales_order_no' => (string)$row->sales_order_no_str,
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

            // -------- LIST (parent only + search + pagination) --------
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $q = SalesInvoiceModel::query()
                ->from('t_sales_invoice as si')
                ->leftJoin('t_clients as c', 'c.id', '=', 'si.client')
                ->leftJoin('t_sales_order as so', 'so.id', '=', 'si.sales_order_no')
                ->select([
                    'si.id','si.invoice_no','si.invoice_date',
                    'si.gross_total','si.total_tax','si.round_off','si.grand_total',
                    'c.id as client_id','c.name as client_name',
                    'so.id as sales_order_id','so.sales_order_no as sales_order_no_str'
                ])
                ->orderBy('si.id','desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('si.invoice_no', 'like', "%{$search}%")
                      ->orWhere('so.sales_order_no', 'like', "%{$search}%")
                      ->orWhere('c.name', 'like', "%{$search}%")
                      ->orWhereExists(function ($sub) use ($search) {
                          $sub->select(DB::raw(1))
                              ->from('t_sales_invoice_products as sip')
                              ->join('t_products as p', 'p.sku', '=', 'sip.sku')
                              ->whereColumn('sip.sales_invoice', 'si.id')
                              ->where('p.item_name', 'like', "%{$search}%");
                      });
                });
            }

            $total = (clone $q)->count();
            $items = $q->skip($offset)->take($limit)->get();

            $data = $items->map(function ($it) {
                return [
                    'id' => (string)$it->id,
                    'invoice_no' => (string)$it->invoice_no,
                    'invoice_date' => $it->invoice_date,

                    'client' => $it->client_id ? [
                        'id' => (string)$it->client_id,
                        'name' => (string)$it->client_name,
                    ] : null,

                    'sales_order' => $it->sales_order_id ? [
                        'id' => (string)$it->sales_order_id,
                        'sales_order_no' => (string)$it->sales_order_no_str,
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
            return $this->serverError($e, 'Sales invoice fetch failed');
        }
    }

    /* ================= UPDATE ================= */
    public function edit(Request $request, $id)
    {
        try {
            $inv = SalesInvoiceModel::find($id);
            if (!$inv) return $this->error('Sales invoice not found.', 404);

            $validator = Validator::make($request->all(), [
                'client' => ['sometimes','integer','exists:t_clients,id'],
                'invoice_date' => ['sometimes','nullable','date'],

                // frontend sends sales_order_no string
                'sales_order_no' => ['sometimes','string','exists:t_sales_order,sales_order_no'],

                'gross_total' => ['sometimes','nullable','numeric'],
                'packing_and_forwarding' => ['sometimes','nullable','numeric'],
                'freight_val' => ['sometimes','nullable','numeric'],
                'total_tax' => ['sometimes','nullable','numeric'],
                'round_off' => ['sometimes','nullable','numeric'],
                'grand_total' => ['sometimes','nullable','numeric'],

                'prices' => ['sometimes','nullable','string','max:255'],
                'p_and_f' => ['sometimes','nullable','string','max:255'],
                'freight' => ['sometimes','nullable','string','max:255'],
                'delivery' => ['sometimes','nullable','string','max:255'],
                'payment' => ['sometimes','nullable','string','max:255'],
                'validity' => ['sometimes','nullable','string','max:255'],
                'remarks' => ['sometimes','nullable','string'],

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

            $salesOrderId = null;
            if (!empty($v['sales_order_no'])) {
                $salesOrderId = DB::table('t_sales_order')
                    ->where('sales_order_no', $v['sales_order_no'])
                    ->value('id');

                if (!$salesOrderId) {
                    throw new \Exception("Invalid sales_order_no. Not found: {$v['sales_order_no']}");
                }
            }

            DB::transaction(function () use ($inv, $v, $salesOrderId) {

                $inv->fill([
                    'client' => $v['client'] ?? $inv->client,
                    'invoice_date' => array_key_exists('invoice_date',$v) ? $v['invoice_date'] : $inv->invoice_date,

                    'sales_order_no' => $salesOrderId ?? $inv->sales_order_no,

                    'gross_total' => array_key_exists('gross_total',$v) ? ($v['gross_total'] ?? 0) : $inv->gross_total,
                    'packing_and_forwarding' => array_key_exists('packing_and_forwarding',$v) ? ($v['packing_and_forwarding'] ?? 0) : $inv->packing_and_forwarding,
                    'freight_val' => array_key_exists('freight_val',$v) ? ($v['freight_val'] ?? 0) : $inv->freight_val,
                    'total_tax' => array_key_exists('total_tax',$v) ? ($v['total_tax'] ?? 0) : $inv->total_tax,
                    'round_off' => array_key_exists('round_off',$v) ? ($v['round_off'] ?? 0) : $inv->round_off,
                    'grand_total' => array_key_exists('grand_total',$v) ? ($v['grand_total'] ?? 0) : $inv->grand_total,

                    'prices' => array_key_exists('prices',$v) ? $v['prices'] : $inv->prices,
                    'p_and_f' => array_key_exists('p_and_f',$v) ? $v['p_and_f'] : $inv->p_and_f,
                    'freight' => array_key_exists('freight',$v) ? $v['freight'] : $inv->freight,
                    'delivery' => array_key_exists('delivery',$v) ? $v['delivery'] : $inv->delivery,
                    'payment' => array_key_exists('payment',$v) ? $v['payment'] : $inv->payment,
                    'validity' => array_key_exists('validity',$v) ? $v['validity'] : $inv->validity,
                    'remarks' => array_key_exists('remarks',$v) ? $v['remarks'] : $inv->remarks,
                ]);

                $inv->save();

                if (isset($v['products'])) {
                    DB::table('t_sales_invoice_products')->where('sales_invoice', $inv->id)->delete();

                    $ins = [];
                    foreach ($v['products'] as $p) {
                        $ins[] = [
                            'sales_invoice' => $inv->id,
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
                    DB::table('t_sales_invoice_products')->insert($ins);
                }
            });

            $fresh = SalesInvoiceModel::with('products')->find($inv->id);

            return $this->success('Sales Invoice updated successfully.', [
                'sales_invoice' => $fresh,
                'file_url'      => null,
            ], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales invoice update failed');
        }
    }

    /* ================= DELETE ================= */
    public function delete($id)
    {
        try {
            $inv = SalesInvoiceModel::find($id);
            if (!$inv) return $this->error('Sales invoice not found.', 404);

            DB::transaction(function () use ($inv) {
                DB::table('t_sales_invoice_products')->where('sales_invoice', $inv->id)->delete();
                $inv->delete();
            });

            return $this->success('Sales invoice deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Sales invoice delete failed');
        }
    }
}
