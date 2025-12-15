<?php

namespace App\Http\Controllers;
use App\Models\BrandModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    //
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation (schema aligned)
            $request->validate([
                'name'     => ['required', 'string', 'max:255'],
                'order_by' => ['nullable', 'integer', 'min:0'],
                'hex_code' => ['nullable', 'string', 'max:9'],
                'logo'     => ['nullable', 'integer'], // FK to uploads table (if exists)
            ]);

            // 2️⃣ Create brand
            $brand = BrandModel::create([
                'name'     => $request->name,
                'order_by' => $request->order_by ?? 0,
                'hex_code' => $request->hex_code,
                'logo'     => $request->logo,
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
}
