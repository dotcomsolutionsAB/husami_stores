<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\ProductStockModel;
use App\Models\ProductModel;
use App\Models\UploadModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductStockController extends Controller
{
    //
    use ApiResponse;

    /**
     * TOKENIZED SEARCH
     * Search tokens across: batch_no, invoice_no, tc_no, remarks
     */
    private function applyTokenSearch($q, string $search): void
    {
        $search = trim($search);
        if ($search === '') return;

        // split by spaces; remove empties
        $tokens = array_values(array_filter(preg_split('/\s+/', $search)));

        foreach ($tokens as $token) {
            $q->where(function ($w) use ($token) {
                $w->where('s.batch_no', 'like', "%{$token}%")
                  ->orWhere('s.invoice_no', 'like', "%{$token}%")
                  ->orWhere('s.tc_no', 'like', "%{$token}%")
                  ->orWhere('s.remarks', 'like', "%{$token}%");
            });
        }
    }

    private function mapTcAttachmentsToPaths($rows)
    {
        // $rows can be Collection (list) or single object
        $isSingle = !($rows instanceof \Illuminate\Support\Collection);

        $collection = $isSingle ? collect([$rows]) : $rows;

        // collect all upload ids from all rows (comma separated)
        $allIds = $collection->flatMap(function ($r) {
            $raw = (string) ($r->tc_attachment ?? '');
            $ids = array_filter(array_map('trim', explode(',', $raw)));
            return collect($ids)->map(fn($x) => (int) $x)->filter(fn($x) => $x > 0);
        })->unique()->values();

        if ($allIds->isEmpty()) {
            // ensure tc_attachment_paths exists
            $collection->each(function ($r) {
                $r->tc_attachment_paths = [];
            });

            return $isSingle ? $collection->first() : $collection;
        }

        // fetch uploads in one query
        $uploadMap = UploadModel::whereIn('id', $allIds)
            ->get(['id', 'file_path']) // file_path like "storage/uploads/..."
            ->keyBy('id');

        $collection->each(function ($r) use ($uploadMap) {
            $raw = (string) ($r->tc_attachment ?? '');
            $ids = array_filter(array_map('trim', explode(',', $raw)));

            $paths = [];
            foreach ($ids as $id) {
                $id = (int) $id;
                if ($id > 0 && $uploadMap->has($id)) {
                    // If you want full URL:
                    $paths[] = asset($uploadMap[$id]->file_path);

                    // If you want only file_path (relative):
                    // $paths[] = $uploadMap[$id]->file_path;
                }
            }

            // keep original ids if you want, but add paths array
            $r->tc_attachment_paths = $paths;

            // OR if you want to replace tc_attachment entirely:
            // $r->tc_attachment = $paths;
        });

        return $isSingle ? $collection->first() : $collection;
    }

    // ✅ CREATE (all non-null required)
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'product_id'    => ['required','integer','exists:t_products,id'],
                'godown_id'     => ['required','integer','exists:t_godown,id'], // if you have t_godown then use exists:t_godown,id
                'quantity'      => ['required','integer','min:0'],
                'ctn'           => ['required','integer','min:0'],
                'batch_no'      => ['required','string','max:255'],
                'rack_no'       => ['required','string','max:255'],
                'invoice_no'    => ['required','string','max:255'],
                'invoice_date'  => ['required','date'],

                'tc_no'         => ['nullable','string','max:255'],
                'tc_date'       => ['nullable','date'],
                'remarks'       => ['nullable','string'],

                // optional attachments on create
                'tc_attachment_files'   => ['nullable','array'],
                'tc_attachment_files.*' => ['file','mimes:jpg,jpeg,png,webp,avif,gif,pdf','max:5120'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $v = $validator->validated();

            $stock = DB::transaction(function () use ($request, $v) {
                $uploadIds = [];

                // store attachments (optional)
                if ($request->hasFile('tc_attachment_files')) {
                    foreach ($request->file('tc_attachment_files') as $file) {
                        $uploadIds[] = $this->storeUpload($file, 'uploads/product_stocks/tc');
                    }
                }

                return ProductStockModel::create([
                    'product_id'    => (int)$v['product_id'],
                    'godown_id'     => (int)$v['godown_id'],
                    'quantity'      => (int)$v['quantity'],
                    'ctn'           => (int)$v['ctn'],
                    'batch_no'      => $v['batch_no'],
                    'rack_no'       => $v['rack_no'],
                    'invoice_no'    => $v['invoice_no'],
                    'invoice_date'  => $v['invoice_date'],
                    'tc_no'         => $v['tc_no'] ?? null,
                    'tc_date'       => $v['tc_date'] ?? null,
                    'remarks'       => $v['remarks'] ?? null,
                    'tc_attachment' => !empty($uploadIds) ? implode(',', $uploadIds) : null,
                ]);
            });

            return $this->success('Data saved successfully', $stock, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product stock create failed');
        }
    }

    // ✅ FETCH (single or list with filters)
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $row = ProductStockModel::with('product')->find($id);
                if (!$row) return $this->error('Record not found.', 404);
                $row = $this->mapTcAttachmentsToPaths($row);

                return $this->success('Data fetched successfully', $row, 200);
            }

            // LIST + pagination
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));

            $search = trim((string)$request->input('search', ''));

            // product filters
            $brand          = trim((string)$request->input('brand', ''));
            $specifications = trim((string)$request->input('specifications', ''));
            $item           = trim((string)$request->input('item', ''));
            $size           = trim((string)$request->input('size', ''));
            $finish         = trim((string)$request->input('finish', ''));

            // stock filters
            $rack_no = trim((string)$request->input('rack_no', ''));

            // JOIN stocks + products
            $q = DB::table('t_product_stocks as s')
                ->join('t_products as p', 'p.id', '=', 's.product_id')
                ->select(
                    's.*',
                    'p.sku',
                    'p.grade_no',
                    'p.item_name',
                    'p.size as product_size',
                    'p.brand as product_brand',
                    'p.finish_type',
                    'p.specifications'
                )
                ->orderBy('s.id', 'desc');

            // tokenized search on stock columns
            $this->applyTokenSearch($q, $search);

            // rack filter
            if ($rack_no !== '') {
                $q->where('s.rack_no', 'like', "%{$rack_no}%");
            }

            // product filters
            if ($brand !== '') {
                $q->where('p.brand', (int)$brand);
            }

            if ($item !== '') {
                $q->where('p.item_name', 'like', "%{$item}%");
            }

            if ($size !== '') {
                $q->where('p.size', 'like', "%{$size}%");
            }

            if ($finish !== '') {
                $q->where('p.finish_type', 'like', "%{$finish}%");
            }

            if ($specifications !== '') {
                // if specifications is big text / JSON / comma string, LIKE works
                $q->where('p.specifications', 'like', "%{$specifications}%");
            }

            $total = (clone $q)->count();

            $items = $q->skip($offset)->take($limit)->get();
            $count = $items->count();
            $items = $this->mapTcAttachmentsToPaths($items);

            return $this->success('Data fetched successfully', $items, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $count,
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product stock fetch failed');
        }
    }

    // ✅ edit (and APPEND tc_attachment files)
    public function edit(Request $request, $id)
    {
        try {
            $stock = ProductStockModel::find($id);
            if (!$stock) return $this->error('Record not found.', 404);

            $validator = Validator::make($request->all(), [
                // allow updates
                'product_id'    => ['sometimes','integer','exists:t_products,id'],
                'godown_id'     => ['sometimes','integer'],
                'quantity'      => ['sometimes','integer','min:0'],
                'ctn'           => ['sometimes','integer','min:0'],
                'batch_no'      => ['sometimes','string','max:255'],
                'rack_no'       => ['sometimes','string','max:255'],
                'invoice_no'    => ['sometimes','string','max:255'],
                'invoice_date'  => ['sometimes','date'],

                'tc_no'         => ['nullable','string','max:255'],
                'tc_date'       => ['nullable','date'],
                'remarks'       => ['nullable','string'],

                // append attachments
                'tc_attachment_files'   => ['nullable','array'],
                'tc_attachment_files.*' => ['file','mimes:jpg,jpeg,png,webp,avif,gif,pdf','max:5120'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $v = $validator->validated();

            DB::transaction(function () use ($request, $stock, $v) {
                // update normal fields
                $stock->update($v);

                // append attachments
                if ($request->hasFile('tc_attachment_files')) {
                    $existing = $this->csvToIntArray($stock->tc_attachment);

                    foreach ($request->file('tc_attachment_files') as $file) {
                        $newId = $this->storeUpload($file, 'uploads/product_stocks/tc');
                        $existing[] = (int)$newId;
                    }

                    $existing = array_values(array_unique(array_filter($existing)));
                    $stock->tc_attachment = !empty($existing) ? implode(',', $existing) : null;
                    $stock->save();
                }
            });

            return $this->success('Data saved successfully', $stock->fresh(), 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product stock update failed');
        }
    }

    // ✅ DELETE
    public function delete($id)
    {
        try {
            $stock = ProductStockModel::find($id);
            if (!$stock) return $this->error('Record not found.', 404);

            DB::transaction(function () use ($stock) {
                // delete all attachments physically + upload rows
                $ids = $this->csvToIntArray($stock->tc_attachment);
                foreach ($ids as $uploadId) {
                    $this->deleteUploadById($uploadId);
                }

                $stock->delete();
            });

            return $this->success('Data saved successfully', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product stock delete failed');
        }
    }

    // ✅ DELETE SINGLE ATTACHMENT (stock_id + attachment_id)
    public function deleteAttachment(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'attachment_id' => ['required','integer','min:1'],
            ]);
            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $stock = ProductStockModel::find($id);
            if (!$stock) return $this->error('Record not found.', 404);

            $attachmentId = (int)$request->input('attachment_id');

            DB::transaction(function () use ($stock, $attachmentId) {
                $ids = $this->csvToIntArray($stock->tc_attachment);

                // remove from csv list
                $ids = array_values(array_filter($ids, fn($x) => (int)$x !== $attachmentId));

                // update stock row
                $stock->tc_attachment = !empty($ids) ? implode(',', $ids) : null;
                $stock->save();

                // delete upload row + physical file
                $this->deleteUploadById($attachmentId);
            });

            return $this->success('Data saved successfully', $stock->fresh(), 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Product stock attachment delete failed');
        }
    }

    // -------------------------
    // Helpers
    // -------------------------

    private function csvToIntArray(?string $csv): array
    {
        if (!$csv) return [];
        return array_values(array_filter(array_map(function ($x) {
            $x = trim((string)$x);
            return ctype_digit($x) ? (int)$x : null;
        }, explode(',', $csv))));
    }

    private function storeUpload($file, string $dir): int
    {
        $ext      = strtolower($file->getClientOriginalExtension());
        $filename = Str::uuid() . '.' . $ext;

        $path = $file->storeAs($dir, $filename, 'public');

        $upload = UploadModel::create([
            'file_name' => $file->getClientOriginalName(),
            'file_ext'  => $ext,
            'file_path'  => 'storage/' . ltrim($path, '/'),
            'file_size' => $file->getSize(),
        ]);

        return (int)$upload->id;
    }

    private function deleteUploadById(int $uploadId): void
    {
        $upload = UploadModel::find($uploadId);
        if (!$upload) return;

        if (!empty($upload->file_path)) {
            $relativePath = str_replace('storage/', '', $upload->file_path);
            Storage::disk('public')->delete($relativePath);
        }

        $upload->delete();
    }
}
