<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\SupplierModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SuppliersController extends Controller
{
    use ApiResponse;

    // CREATE
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'            => ['required', 'string', 'max:255'],
                'address_line_1'  => ['nullable', 'string', 'max:255'],
                'address_line_2'  => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],
                'pincode'         => ['nullable', 'integer', 'digits_between:4,8'],
                'gstin'           => ['nullable', 'string', 'max:20'],
                'state'           => ['nullable', 'integer', 'exists:t_state,id'],
                'country'         => ['nullable', 'string', 'max:64'],
                'mobile'          => ['nullable', 'string', 'max:32'],
                'email'           => ['nullable', 'email', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            // Composite uniqueness
            $exists = SupplierModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->exists();

            if ($exists) {
                return $this->error(
                    'Supplier with same Name, Mobile and GSTIN already exists.',
                    409
                );
            }

            $supplier = SupplierModel::create($validator->validated());

            return $this->success('Supplier created successfully.', $supplier, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Supplier create failed');
        }
    }

    // FETCH
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $supplier = SupplierModel::find($id);

                if (!$supplier) {
                    return $this->error('Supplier not found.', 404);
                }

                return $this->success('Data fetched successfully', $supplier, 200);
            }

            // LIST
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $q = SupplierModel::orderBy('id', 'desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
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
            return $this->serverError($e, 'Supplier fetch failed');
        }
    }

    // EDIT
    public function edit(Request $request, $id)
    {
        try {
            $supplier = SupplierModel::find($id);

            if (!$supplier) {
                return $this->error('Supplier not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name'            => ['required', 'string', 'max:255'],
                'address_line_1'  => ['nullable', 'string', 'max:255'],
                'address_line_2'  => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],
                'pincode'         => ['nullable', 'integer', 'digits_between:4,8'],
                'gstin'           => ['nullable', 'string', 'max:20'],
                'state'           => ['nullable', 'integer', 'exists:t_state,id'],
                'country'         => ['nullable', 'string', 'max:64'],
                'mobile'          => ['nullable', 'string', 'max:32'],
                'email'           => ['nullable', 'email', 'max:255'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            // Composite uniqueness (exclude current)
            $exists = SupplierModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return $this->error(
                    'Supplier with same Name, Mobile and GSTIN already exists.',
                    409
                );
            }

            $supplier->update($validator->validated());

            return $this->success(
                'Supplier updated successfully.',
                $supplier->fresh(),
                200
            );

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Supplier update failed');
        }
    }

    // DELETE
    public function delete($id)
    {
        try {
            $supplier = SupplierModel::find($id);

            if (!$supplier) {
                return $this->error('Supplier not found.', 404);
            }

            $supplier->delete();

            return $this->success('Supplier deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Supplier delete failed');
        }
    }

    // EXPORT CSV
    public function exportExcel(Request $request)
    {
        try {
            $search = trim((string) $request->input('search', ''));

            $q = SupplierModel::orderBy('id', 'desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            $suppliers = $q->get();

            if ($suppliers->isEmpty()) {
                return $this->error('No suppliers found.', 404);
            }

            $dir = storage_path('app/public/exports/suppliers');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $fileName = 'suppliers_' . now()->format('Ymd_His') . '.csv';
            $filePath = $dir . '/' . $fileName;

            $file = fopen($filePath, 'w');

            fputcsv($file, [
                'ID','Name','Address Line 1','Address Line 2','City',
                'Pincode','GSTIN','State','Country','Mobile','Email',
                'Created At','Updated At'
            ]);

            foreach ($suppliers as $s) {
                fputcsv($file, [
                    $s->id, $s->name, $s->address_line_1, $s->address_line_2,
                    $s->city, $s->pincode, $s->gstin, $s->state,
                    $s->country, $s->mobile, $s->email,
                    $s->created_at, $s->updated_at,
                ]);
            }

            fclose($file);

            return $this->success('CSV exported successfully.', [
                'file_name' => $fileName,
                'url'       => asset('storage/exports/suppliers/' . $fileName),
            ], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Supplier export failed');
        }
    }
}