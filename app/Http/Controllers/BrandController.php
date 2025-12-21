<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\BrandModel;
use App\Models\UploadModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    use ApiResponse;

    // CREATE
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validate
            $validator = Validator::make($request->all(), [
                'name'     => ['required', 'string', 'max:255'],
                'order_by' => ['nullable', 'integer', 'min:0'],
                'hex_code' => ['nullable', 'string', 'max:9'],
                'logo'     => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $logoUploadId = null;

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');

                $path = $file->store('brands/logos', 'public');

                $upload = UploadModel::create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_ext'  => strtolower($file->getClientOriginalExtension()),
                    'file_size' => (int) $file->getSize(),
                ]);

                $logoUploadId = $upload->id;
            }

            // 2️⃣ Create brand
            $brand = BrandModel::create([
                'name'     => $request->name,
                'order_by' => $request->order_by ?? 0,
                'hex_code' => $request->hex_code,
                'logo'     => $logoUploadId,
            ]);

            return $this->success('Brand created successfully.', $brand, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Brand create failed');
        }
    }

    // FETCH
    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE BRAND
            if ($id !== null) {
                $brand = BrandModel::with('logoRef:id,file_path')->find($id);

                if (!$brand) {
                    return $this->error('Brand not found.', 404);
                }

                return $this->success('Data fetched successfully', [
                    'id'       => $brand->id,
                    'name'     => $brand->name,
                    'order_by' => $brand->order_by,
                    'hex_code' => $brand->hex_code,
                    'logo'     => $brand->logoRef
                        ? [
                            'id'  => $brand->logoRef->id,
                            'url' => asset('storage/' . ltrim($brand->logoRef->file_path, '/')),
                        ]
                        : null,
                ], 200);
            }

            // 🔹 LIST BRANDS
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $q = BrandModel::with('logoRef:id,file_path')
                ->orderBy('order_by', 'asc')
                ->orderBy('id', 'desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            $total = (clone $q)->count();

            $items = $q->skip($offset)->take($limit)->get();

            $data = $items->map(function ($b) {
                return [
                    'id'       => $b->id,
                    'name'     => $b->name,
                    'order_by' => $b->order_by,
                    'hex_code' => $b->hex_code,
                    'logo'     => $b->logoRef
                        ? [
                            'id'  => $b->logoRef->id,
                            'url' => asset('storage/' . ltrim($b->logoRef->file_path, '/')),
                        ]
                        : null,
                ];
            });

            return $this->success('Data fetched successfully', $data, 200, [
                'pagination' => [
                    'limit'  => $limit,
                    'offset' => $offset,
                    'count'  => $data->count(),
                    'total'  => $total,
                ]
            ]);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Brand fetch failed');
        }
    }
}