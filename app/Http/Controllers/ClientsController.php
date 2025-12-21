<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use App\Models\ClientModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ClientsController extends Controller
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
            $exists = ClientModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->exists();

            if ($exists) {
                return $this->error(
                    'Client with same Name, Mobile and GSTIN already exists.',
                    409
                );
            }

            $client = ClientModel::create($validator->validated());

            return $this->success('Client created successfully.', $client, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Client create failed');
        }
    }

    // FETCH
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $client = ClientModel::find($id);

                if (!$client) {
                    return $this->error('Client not found.', 404);
                }

                return $this->success('Data fetched successfully', $client, 200);
            }

            // LIST
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));

            $q = ClientModel::orderBy('id', 'desc');

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
            return $this->serverError($e, 'Client fetch failed');
        }
    }

    // EDIT
    public function edit(Request $request, $id)
    {
        try {
            $client = ClientModel::find($id);

            if (!$client) {
                return $this->error('Client not found.', 404);
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
            $exists = ClientModel::where('name', $request->name)
                ->where('mobile', $request->mobile)
                ->where('gstin', $request->gstin)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return $this->error(
                    'Client with same Name, Mobile and GSTIN already exists.',
                    409
                );
            }

            $client->update($validator->validated());

            return $this->success('Client updated successfully.', $client->fresh(), 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Client update failed');
        }
    }

    // DELETE
    public function delete($id)
    {
        try {
            $client = ClientModel::find($id);

            if (!$client) {
                return $this->error('Client not found.', 404);
            }

            $client->delete();

            return $this->success('Client deleted successfully.', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Client delete failed');
        }
    }

    // EXPORT CSV
    public function exportExcel(Request $request)
    {
        try {
            $search = trim((string) $request->input('search', ''));

            $q = ClientModel::orderBy('id', 'desc');

            if ($search !== '') {
                $q->where('name', 'like', "%{$search}%");
            }

            $clients = $q->get();

            if ($clients->isEmpty()) {
                return $this->error('No clients found.', 404);
            }

            $dir = storage_path('app/public/exports/clients');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            $fileName = 'clients_' . now()->format('Ymd_His') . '.csv';
            $filePath = $dir . '/' . $fileName;

            $file = fopen($filePath, 'w');

            fputcsv($file, [
                'ID','Name','Address Line 1','Address Line 2','City',
                'Pincode','GSTIN','State','Country','Mobile','Email',
                'Created At','Updated At'
            ]);

            foreach ($clients as $c) {
                fputcsv($file, [
                    $c->id, $c->name, $c->address_line_1, $c->address_line_2,
                    $c->city, $c->pincode, $c->gstin, $c->state,
                    $c->country, $c->mobile, $c->email,
                    $c->created_at, $c->updated_at,
                ]);
            }

            fclose($file);

            return $this->success('CSV exported successfully.', [
                'file_name' => $fileName,
                'url'       => asset('storage/exports/clients/' . $fileName),
            ], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Client export failed');
        }
    }
}