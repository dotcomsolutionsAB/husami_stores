<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Traits\ApiResponse;
use App\Models\ProformaModel;
use App\Models\ProformaProductModel;
use App\Models\CounterModel;

class ProformaController extends Controller
{
    //
    use ApiResponse;

    /* ============================================================
       CREATE
    ============================================================ */
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client'       => ['required','integer','exists:t_clients,id'],
                'proforma_no'  => ['required','string','max:255','unique:t_proforma,proforma_no'],
                'proforma_date'=> ['nullable','date'],
                'quotation'    => ['required','integer','exists:t_quotation,id'],
                'sales_order_no'=>['nullable','string','max:255'],

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

            $p = DB::transaction(function () use ($v) {

                // ✅ counter lock + verify (name = proforma)
                $counter = CounterModel::where('name', 'proforma')
                    ->lockForUpdate()
                    ->first();

                if (!$counter) {
                    throw new \Exception("Counter 'proforma' not found.");
                }

                $expectedNo = $counter->formatted;
                if (trim((string)$v['proforma_no']) !== $expectedNo) {
                    throw new \Exception("Invalid proforma_no. Expected: {$expectedNo}");
                }

                // ✅ create header (file stays null)
                $proforma = ProformaModel::create([
                    'client'        => (int)$v['client'],
                    'proforma_no'   => $v['proforma_no'],
                    'proforma_date' => $v['proforma_date'] ?? null,
                    'quotation'     => (int)$v['quotation'],
                    'sales_order_no'=> $v['sales_order_no'] ?? null,

                    'gross_total' => $v['gross_total'] ?? 0,
                    'packing_and_forwarding' => $v['packing_and_forwarding'] ?? 0,
                    'freight_val' => $v['freight_val'] ?? 0,
                    'total_tax'   => $v['total_tax'] ?? 0,
                    'round_off'   => $v['round_off'] ?? 0,
                    'grand_total' => $v['grand_total'] ?? 0,

                    'prices'   => $v['prices'] ?? null,
                    'p_and_f'  => $v['p_and_f'] ?? null,
                    'freight'  => $v['freight'] ?? null,
                    'delivery' => $v['delivery'] ?? null,
                    'payment'  => $v['payment'] ?? null,
                    'validity' => $v['validity'] ?? null,
                    'remarks'  => $v['remarks'] ?? null,

                    'file' => null,
                ]);

                // ✅ insert products
                $ins = [];
                foreach ($v['products'] as $it) {
                    $ins[] = [
                        'proforma'   => $proforma->id,
                        'sku'        => (string)$it['sku'],
                        'qty'        => (int)$it['qty'],
                        'unit'       => isset($it['unit']) ? (int)$it['unit'] : null,
                        'price'      => isset($it['price']) ? (float)$it['price'] : 0,
                        'discount'   => isset($it['discount']) ? (float)$it['discount'] : 0,
                        'hsn'        => $it['hsn'] ?? null,
                        'tax'        => isset($it['tax']) ? (float)$it['tax'] : 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                DB::table('t_proforma_products')->insert($ins);

                // ✅ increment counter AFTER success
                $counter->number = (int)$counter->number + 1;
                $counter->save();

                return $proforma;
            });

            $fresh = ProformaModel::with(['products'])->find($p->id);

            return $this->success('Proforma created successfully.', [
                'proforma' => $fresh,
            ], 201);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Proforma create failed');
        }
    }

    /* ============================================================
       FETCH (LIST + SINGLE)
       - single: returns parent + child
       - list: returns parent only
    ============================================================ */
    public function fetch(Request $request, $id = null)
    {
        try {
            // -------- SINGLE --------
            if ($id !== null) {
                $row = ProformaModel::query()
                    ->select([
                        'id','client','proforma_no','proforma_date','quotation','sales_order_no',
                        'gross_total','packing_and_forwarding','freight_val','total_tax','round_off','grand_total',
                        'prices','p_and_f','freight','delivery','payment','validity','remarks','file'
                    ])
                    ->with(['products' => function ($p) {
                        $p->select(['id','proforma','sku','qty','unit','price','discount','hsn','tax']);
                    }])
                    ->find($id);

                if (!$row) return $this->error('Proforma not found.', 404);

                return $this->success('Data fetched successfully', [
                    'proforma' => $row,
                    'products' => $row->products,
                ], 200);
            }

            // -------- LIST --------
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $client   = trim((string) $request->input('client', ''));
            $quotation= trim((string) $request->input('quotation', ''));

            $q = ProformaModel::query()
                ->select([
                    'id','client','proforma_no','proforma_date','quotation','sales_order_no',
                    'gross_total','total_tax','round_off','grand_total','file'
                ])
                ->orderBy('id', 'desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('proforma_no', 'like', "%{$search}%")
                      ->orWhere('sales_order_no', 'like', "%{$search}%");
                });
            }

            if ($client !== '') $q->where('client', (int)$client);
            if ($quotation !== '') $q->where('quotation', (int)$quotation);

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
            return $this->serverError($e, 'Proforma fetch failed');
        }
    }

    /* ============================================================
       Edit
       - No counter check here
       - No proforma_no change (kept stable)
       - If products passed, replace child rows
    ============================================================ */
    public function edit(Request $request, $id)
    {
        try {
            $proforma = ProformaModel::find($id);
            if (!$proforma) return $this->error('Proforma not found.', 404);

            $validator = Validator::make($request->all(), [
                'client'       => ['sometimes','integer','exists:t_clients,id'],
                'proforma_date'=> ['sometimes','nullable','date'],
                'quotation'    => ['sometimes','integer','exists:t_quotation,id'],
                'sales_order_no'=>['sometimes','nullable','string','max:255'],

                'gross_total' => ['sometimes','nullable','numeric'],
                'packing_and_forwarding' => ['sometimes','nullable','numeric'],
                'freight_val' => ['sometimes','nullable','numeric'],
                'total_tax'   => ['sometimes','nullable','numeric'],
                'round_off'   => ['sometimes','nullable','numeric'],
                'grand_total' => ['sometimes','nullable','numeric'],

                'prices'   => ['sometimes','nullable','string','max:255'],
                'p_and_f'  => ['sometimes','nullable','string','max:255'],
                'freight'  => ['sometimes','nullable','string','max:255'],
                'delivery' => ['sometimes','nullable','string','max:255'],
                'payment'  => ['sometimes','nullable','string','max:255'],
                'validity' => ['sometimes','nullable','string','max:255'],
                'remarks'  => ['sometimes','nullable','string'],

                // products optional
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

            DB::transaction(function () use ($proforma, $v) {

                // update header
                $proforma->fill([
                    'client'        => $v['client']        ?? $proforma->client,
                    'proforma_date' => array_key_exists('proforma_date',$v) ? $v['proforma_date'] : $proforma->proforma_date,
                    'quotation'     => $v['quotation']     ?? $proforma->quotation,
                    'sales_order_no'=> array_key_exists('sales_order_no',$v) ? $v['sales_order_no'] : $proforma->sales_order_no,

                    'gross_total' => array_key_exists('gross_total',$v) ? ($v['gross_total'] ?? 0) : $proforma->gross_total,
                    'packing_and_forwarding' => array_key_exists('packing_and_forwarding',$v) ? ($v['packing_and_forwarding'] ?? 0) : $proforma->packing_and_forwarding,
                    'freight_val' => array_key_exists('freight_val',$v) ? ($v['freight_val'] ?? 0) : $proforma->freight_val,
                    'total_tax'   => array_key_exists('total_tax',$v) ? ($v['total_tax'] ?? 0) : $proforma->total_tax,
                    'round_off'   => array_key_exists('round_off',$v) ? ($v['round_off'] ?? 0) : $proforma->round_off,
                    'grand_total' => array_key_exists('grand_total',$v) ? ($v['grand_total'] ?? 0) : $proforma->grand_total,

                    'prices'   => array_key_exists('prices',$v) ? $v['prices'] : $proforma->prices,
                    'p_and_f'  => array_key_exists('p_and_f',$v) ? $v['p_and_f'] : $proforma->p_and_f,
                    'freight'  => array_key_exists('freight',$v) ? $v['freight'] : $proforma->freight,
                    'delivery' => array_key_exists('delivery',$v) ? $v['delivery'] : $proforma->delivery,
                    'payment'  => array_key_exists('payment',$v) ? $v['payment'] : $proforma->payment,
                    'validity' => array_key_exists('validity',$v) ? $v['validity'] : $proforma->validity,
                    'remarks'  => array_key_exists('remarks',$v) ? $v['remarks'] : $proforma->remarks,
                ]);

                $proforma->save();

                // replace products if provided
                if (isset($v['products'])) {
                    DB::table('t_proforma_products')->where('proforma', $proforma->id)->delete();

                    $ins = [];
                    foreach ($v['products'] as $it) {
                        $ins[] = [
                            'proforma'   => $proforma->id,
                            'sku'        => (string)$it['sku'],
                            'qty'        => (int)$it['qty'],
                            'unit'       => isset($it['unit']) ? (int)$it['unit'] : null,
                            'price'      => isset($it['price']) ? (float)$it['price'] : 0,
                            'discount'   => isset($it['discount']) ? (float)$it['discount'] : 0,
                            'hsn'        => $it['hsn'] ?? null,
                            'tax'        => isset($it['tax']) ? (float)$it['tax'] : 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                    DB::table('t_proforma_products')->insert($ins);
                }
            });

            $fresh = ProformaModel::with('products')->find($proforma->id);

            return $this->success('Proforma updated successfully.', [
                'proforma' => $fresh
            ], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Proforma update failed');
        }
    }

    /* ============================================================
       DELETE
    ============================================================ */
    public function delete($id)
    {
        try {
            $proforma = ProformaModel::find($id);
            if (!$proforma) return $this->error('Proforma not found.', 404);

            DB::transaction(function () use ($proforma) {
                DB::table('t_proforma_products')->where('proforma', $proforma->id)->delete();
                $proforma->delete();
            });

            return $this->success('Proforma deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Proforma delete failed');
        }
    }
}
