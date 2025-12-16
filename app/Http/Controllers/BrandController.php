<?php

namespace App\Http\Controllers;
use App\Models\BrandModel;
use App\Models\UploadModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    // create
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation (schema aligned)
            $request->validate([
                'name'     => ['required', 'string', 'max:255'],
                'order_by' => ['nullable', 'integer', 'min:0'],
                'hex_code' => ['nullable', 'string', 'max:9'],
                'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'], // 5MB
            ]);

            $logoUploadId = null;

            if ($request->hasFile('logo')) {
                $file = $request->file('logo');

                $path = $file->store('brands/logos', 'public');

                // Get extension safely (lowercase)
                $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');

                // Size in bytes
                $size = (int) $file->getSize();

                $upload = UploadModel::create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_ext'  => $ext,   
                    'file_size' => $size, 
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

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Brand created successfully.',
                'data'    => $brand,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Brand create failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating brand.',
            ], 500);
        }
    }

    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE BRAND
            if ($id !== null) {
                $brand = BrandModel::with('logoRef:id,file_path')
                    ->find($id);

                if (! $brand) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Brand not found.',
                    ], 404);
                }

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'data' => [
                        'id' => $brand->id,
                        'name' => $brand->name,
                        'order_by' => $brand->order_by,
                        'hex_code' => $brand->hex_code,
                        'logo' => $brand->logoRef
                            ? ['id'=>$brand->logoRef->id,'url'=>$brand->logoRef->file_path]
                            : null,
                    ],
                ], 200);
            }

            // 🔹 LIST BRANDS
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $total = BrandModel::count();

            $q = BrandModel::with('logoRef:id,file_path')
                ->orderBy('order_by','asc')
                ->orderBy('id','desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            $items = $q->skip($offset)->take($limit)->get();

            $data = $items->map(function ($b) {
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'order_by' => $b->order_by,
                    'hex_code' => $b->hex_code,
                    'logo' => $brand->logoRef
                        ? [
                            'id'  => $brand->logoRef->id,
                            'url' => asset('storage/' . ltrim($brand->logoRef->file_path, '/')),
                        ]
                        : null,
                ];
            });

            return response()->json([
                'code' => 200,
                'status' => true,
                'total' => $total,
                'count' => $data->count(),
                'data' => $data,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Brand fetch failed', ['error'=>$e->getMessage()]);
            return response()->json(['message'=>'Failed to fetch brands'], 500);
        }
    }
}
