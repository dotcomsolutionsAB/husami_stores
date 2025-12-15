<?php

namespace App\Http\Controllers;
use App\Models\ClientModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    //
    public function create(Request $request)
    {
        try {
            // 1️⃣ Validation (schema aligned)
            $request->validate([
                'name'            => ['required', 'string', 'max:255'],

                'address_line_1'  => ['nullable', 'string', 'max:255'],
                'address_line_2'  => ['nullable', 'string', 'max:255'],
                'city'            => ['nullable', 'string', 'max:255'],

                'pincode'         => ['nullable', 'integer', 'digits_between:4,8'],
                'gstin'           => ['nullable', 'string', 'max:20'],

                // FK → t_state.id
                'state'           => ['nullable', 'integer', 'exists:t_state,id'],

                'country'         => ['nullable', 'string', 'max:64'],
                'mobile'          => ['nullable', 'string', 'max:32'],
                'email'           => ['nullable', 'email', 'max:255'],
            ]);

            // 2️⃣ Composite uniqueness check (name + mobile + gstin)
            $exists = ClientModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->exists();

            if ($exists) {
                return response()->json([
                    'code'    => 409,
                    'status'  => false,
                    'message' => 'Client with same Name, Mobile and GSTIN already exists.',
                ], 409);
            }

            // 3️⃣ Create client
            $client = ClientModel::create($request->only([
                'name',
                'address_line_1',
                'address_line_2',
                'city',
                'pincode',
                'gstin',
                'state',
                'country',
                'mobile',
                'email',
            ]));

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Client created successfully.',
                'data'    => $client,
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'   => 422,
                'status' => false,
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Client create failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while creating client.',
            ], 500);
        }
    }
}
