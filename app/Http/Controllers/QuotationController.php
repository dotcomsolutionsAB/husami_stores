<?php

namespace App\Http\Controllers;
use App\Models\QuotationModel;
use App\Models\QuotationProductModel;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    //
    use ApiResponse;

    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'client' => 'required|integer|exists:t_clients,id',
                'quotation' => 'required|string|max:255|unique:t_quotation,quotation',
                'quotation_date' => 'nullable|date',
                'enquiry' => 'nullable|string|max:255',
                'enquiry_date' => 'nullable|date',
                'template' => 'required|integer|exists:t_template,id',

                'gross_total' => 'nullable|numeric',
                'packing_and_forwarding' => 'nullable|numeric',
                'freight_val' => 'nullable|numeric',
                'total_tax' => 'nullable|numeric',
                'round_off' => 'nullable|numeric',
                'grand_total' => 'nullable|numeric',

                'prices' => 'nullable|string|max:255',
                'p_and_f' => 'nullable|string|max:255',
                'freight' => 'nullable|string|max:255',
                'delivery' => 'nullable|string|max:255',
                'payment' => 'nullable|string|max:255',
                'validity' => 'nullable|string|max:255',
                'remarks' => 'nullable|string',

                // products
                'products' => 'required|array|min:1',
                'products.*.sku' => 'required|integer|exists:t_products,sku',      // as per schema unsignedBigInteger
                'products.*.qty' => 'required|integer|min:1',
                'products.*.unit' => 'nullable|integer',
                'products.*.price' => 'nullable|numeric',
                'products.*.discount' => 'nullable|numeric',
                'products.*.hsn' => 'nullable|string|max:32',
                'products.*.tax' => 'nullable|numeric',

                // file upload (optional)
                'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            DB::beginTransaction();

            // ✅ upload file if provided
            $uploadId = null;
            $fileUrl  = null;

            if ($request->hasFile('file')) {
                Storage::disk('public')->makeDirectory('quotation');

                $f = $request->file('file');
                $ext = strtolower($f->getClientOriginalExtension() ?: 'pdf');
                $fileName = 'quotation_' . $request->input('quotation') . '_' . now()->format('Ymd_His') . '.' . $ext;

                $relativePath = 'quotation/' . $fileName;
                Storage::disk('public')->putFileAs('quotation', $f, $fileName);

                // Insert into t_uploads (same pattern)
                $uploadId = DB::table('t_uploads')->insertGetId([
                    'file_name' => $fileName,
                    'file_path' => $relativePath,  // IMPORTANT: relative path on public disk
                    'file_ext'  => $ext,
                    'file_size' => $f->getSize(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // ✅ URL for response (NOT stored in DB)
                $fileUrl = Storage::disk('public')->url($relativePath);
            }

            // ✅ create quotation header
            $q = QuotationModel::create([
                'client' => (int)$request->client,
                'quotation' => $request->quotation,
                'quotation_date' => $request->quotation_date,
                'enquiry' => $request->enquiry,
                'enquiry_date' => $request->enquiry_date,
                'template' => (int)$request->template,

                'gross_total' => $request->gross_total ?? 0,
                'packing_and_forwarding' => $request->packing_and_forwarding ?? 0,
                'freight_val' => $request->freight_val ?? 0,
                'total_tax' => $request->total_tax ?? 0,
                'round_off' => $request->round_off ?? 0,
                'grand_total' => $request->grand_total ?? 0,

                'prices' => $request->prices,
                'p_and_f' => $request->p_and_f,
                'freight' => $request->freight,
                'delivery' => $request->delivery,
                'payment' => $request->payment,
                'validity' => $request->validity,
                'remarks' => $request->remarks,

                'file' => $uploadId,
            ]);

            // ✅ insert products
            $ins = [];
            foreach ($request->products as $p) {
                $ins[] = [
                    'quotation' => $q->id,                 // ✅ FK stores t_quotation.id
                    'sku' => (int)$p['sku'],
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
            DB::table('t_quotation_products')->insert($ins);

            DB::commit();

            // ✅ return fresh with products
            $fresh = QuotationModel::with('products')->find($q->id);

            // if fileUrl not set but file id exists (safety)
            if (!$fileUrl && $fresh->file) {
                $fileUrl = DB::table('t_uploads')->where('id', $fresh->file)->value('file_url');
            }

            return $this->success('Quotation created successfully.', [
                'quotation' => $fresh,
                'file_url'  => $fileUrl,
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->serverError($e, 'Quotation create failed');
        }
    }

    // ✅ LIST + SINGLE (same function)
    public function fetch(Request $request, $id = null)
    {
        try {
            // ---------------- SINGLE ----------------
            if ($id !== null) {
                $row = QuotationModel::with(['products'])->find($id);

                if (!$row) return $this->error('Quotation not found.', 404);

                $fileUrl = null;
                if (!empty($row->file)) {
                    $upload = DB::table('t_uploads')->where('id', $row->file)->first();
                    if ($upload && !empty($upload->file_path)) {
                        $fileUrl = Storage::disk('public')->url($upload->file_path);
                    }
                }

                return $this->success('Data fetched successfully', [
                    'quotation' => $row,
                    'file_url'  => $fileUrl,
                ], 200);
            }

            // ---------------- LIST ----------------
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $client = trim((string) $request->input('client', ''));
            $template = trim((string) $request->input('template', ''));

            $q = QuotationModel::query()
                ->orderBy('id', 'desc');

            // search on quotation or enquiry (you can extend)
            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('quotation', 'like', "%{$search}%")
                      ->orWhere('enquiry', 'like', "%{$search}%");
                });
            }

            if ($client !== '') {
                $q->where('client', (int) $client);
            }

            if ($template !== '') {
                $q->where('template', (int) $template);
            }

            $total = (clone $q)->count();

            $items = $q->skip($offset)->take($limit)->get();
            $count = $items->count();

            // attach file_url in batch (optional but useful)
            $fileIds = $items->pluck('file')->filter()->unique()->values()->all();

            $uploadMap = empty($fileIds)
                ? collect()
                : DB::table('t_uploads')->whereIn('id', $fileIds)->pluck('file_path', 'id');

            $items = $items->map(function ($it) use ($uploadMap) {
                $it->file_url = null;
                if ($it->file && isset($uploadMap[$it->file]) && $uploadMap[$it->file]) {
                    $it->file_url = Storage::disk('public')->url($uploadMap[$it->file]);
                }
                return $it;
            });

            return $this->success('Data fetched successfully', $items, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $count,
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Quotation fetch failed');
        }
    }

    // ✅ Edit (header + products + optional file)
    public function edit(Request $request, $id)
    {
        try {
            $row = QuotationModel::find($id);
            if (!$row) return $this->error('Quotation not found.', 404);

            $validator = Validator::make($request->all(), [
                'client' => 'required|integer|exists:t_clients,id',
                'quotation' => 'required|string|max:255|unique:t_quotation,quotation,' . $id,
                'quotation_date' => 'nullable|date',
                'enquiry' => 'nullable|string|max:255',
                'enquiry_date' => 'nullable|date',
                'template' => 'required|integer',

                'gross_total' => 'nullable|numeric',
                'packing_and_forwarding' => 'nullable|numeric',
                'freight_val' => 'nullable|numeric',
                'total_tax' => 'nullable|numeric',
                'round_off' => 'nullable|numeric',
                'grand_total' => 'nullable|numeric',

                'prices' => 'nullable|string|max:255',
                'p_and_f' => 'nullable|string|max:255',
                'freight' => 'nullable|string|max:255',
                'delivery' => 'nullable|string|max:255',
                'payment' => 'nullable|string|max:255',
                'validity' => 'nullable|string|max:255',
                'remarks' => 'nullable|string',

                'products' => 'required|array|min:1',
                'products.*.sku' => 'required|integer|exists:t_products,sku',         // as per schema unsignedBigInteger
                'products.*.qty' => 'required|integer|min:1',
                'products.*.unit' => 'nullable|integer',
                'products.*.price' => 'nullable|numeric',
                'products.*.discount' => 'nullable|numeric',
                'products.*.hsn' => 'nullable|string|max:32',
                'products.*.tax' => 'nullable|numeric',

                'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            DB::beginTransaction();

            // ---------- FILE UPLOAD ----------
            $uploadId = $row->file; // keep old

            if ($request->hasFile('file')) {
                Storage::disk('public')->makeDirectory('quotation');

                $f = $request->file('file');
                $ext = strtolower($f->getClientOriginalExtension() ?: 'pdf');
                $fileName = 'quotation_' . $request->input('quotation') . '_' . now()->format('Ymd_His') . '.' . $ext;

                $relativePath = 'quotation/' . $fileName;
                Storage::disk('public')->putFileAs('quotation', $f, $fileName);

                // ✅ Insert upload record (your schema)
                $uploadId = DB::table('t_uploads')->insertGetId([
                    'file_name' => $fileName,
                    'file_path' => $relativePath,
                    'file_ext'  => $ext,
                    'file_size' => $f->getSize(),
                ]);
            }

            // ---------- UPDATE HEADER ----------
            $row->update([
                'client' => (int)$request->client,
                'quotation' => $request->quotation,
                'quotation_date' => $request->quotation_date,
                'enquiry' => $request->enquiry,
                'enquiry_date' => $request->enquiry_date,
                'template' => (int)$request->template,

                'gross_total' => $request->gross_total ?? 0,
                'packing_and_forwarding' => $request->packing_and_forwarding ?? 0,
                'freight_val' => $request->freight_val ?? 0,
                'total_tax' => $request->total_tax ?? 0,
                'round_off' => $request->round_off ?? 0,
                'grand_total' => $request->grand_total ?? 0,

                'prices' => $request->prices,
                'p_and_f' => $request->p_and_f,
                'freight' => $request->freight,
                'delivery' => $request->delivery,
                'payment' => $request->payment,
                'validity' => $request->validity,
                'remarks' => $request->remarks,

                'file' => $uploadId,
            ]);

            // ---------- REPLACE PRODUCTS ----------
            QuotationProductModel::where('quotation', $row->id)->delete();

            $ins = [];
            foreach ($request->products as $p) {
                $ins[] = [
                    'quotation' => $row->id,
                    'sku' => (int)$p['sku'],
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
            DB::table('t_quotation_products')->insert($ins);

            DB::commit();

            $fresh = QuotationModel::with('products')->find($row->id);
            $fileUrl = $fresh->file ? DB::table('t_uploads')->where('id', $fresh->file)->value('file_url') : null;

            return $this->success('Quotation updated successfully.', [
                'quotation' => $fresh,
                'file_url'  => $fileUrl,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->serverError($e, 'Quotation update failed');
        }
    }

    // ✅ DELETE (header + products + optionally delete file record + physical file)
    public function delete($id)
    {
        try {
            $row = QuotationModel::find($id);
            if (!$row) return $this->error('Quotation not found.', 404);

            DB::beginTransaction();

            QuotationProductModel::where('quotation', $row->id)->delete();

            // optional file delete
            if (!empty($row->file)) {
                $upload = DB::table('t_uploads')->where('id', $row->file)->first();
                if ($upload && !empty($upload->file_path)) {
                    Storage::disk('public')->delete($upload->file_path);
                }
                DB::table('t_uploads')->where('id', $row->file)->delete();
            }

            $row->delete();

            DB::commit();

            return $this->success('Quotation deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            DB::rollBack();
            return $this->serverError($e, 'Quotation delete failed');
        }
    }
}
