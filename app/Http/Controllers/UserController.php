<?php

namespace App\Http\Controllers;
use App\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    //
    use ApiResponse;
    // Register API
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:255',
                'email'    => 'nullable|email|unique:users,email',
                'password' => 'required|string|min:6|confirmed',
                'username' => 'required|string|max:255|unique:users,username',
                'role'     => 'required|in:admin,user',
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'username' => $request->username,
                'role'     => $request->role,
            ]);

            return $this->success('Data saved successfully', $user, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'User create failed');
        }
    }

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            // SINGLE
            if ($id !== null) {
                $user = User::select('id','name','email','username','role','created_at')->find($id);

                if (!$user) {
                    return $this->error('User not found.', 404);
                }

                return $this->success('Data fetched successfully', $user, 200);
            }

            // LIST (with pagination)
            $limit  = max(1, (int) $request->input('limit', 10));
            $offset = max(0, (int) $request->input('offset', 0));
            $search = trim((string) $request->input('search', ''));
            $role = trim((string) $request->input('role', ''));

            $q = User::select('id','name','email','username','role','created_at')
                ->orderBy('id','desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
                });
            }

            if ($role !== '') {
                $q->where('role', $role);
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
            return $this->serverError($e, 'User fetch failed');
        }
    }

    // update password
    public function updatePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => ['required', 'string', 'exists:users,username'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $user = User::where('username', $request->username)->first();

            if (!$user) {
                return $this->error('User not found.', 404);
            }

            $user->password = Hash::make($request->password);
            $user->save();

            return $this->success('Data saved successfully', $user, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Password update failed');
        }
    }

    // update
    public function edit(Request $request, $id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->error('User not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'name'     => 'required|string|max:255',
                'email'    => 'nullable|email|unique:users,email,' . $user->id,
                'username' => 'required|string|max:255|unique:users,username,' . $user->id,
                'role'     => 'required|in:admin,user,sub-admin',
                'password' => 'nullable|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return $this->validation($validator);
            }

            $updateData = [
                'name'     => $request->name,
                'email'    => $request->email,
                'username' => $request->username,
                'role'     => $request->role,
            ];

            if (!empty($request->password)) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return $this->success('Data saved successfully', $user, 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'User update failed');
        }
    }

    // delete
    public function delete($id)
    {
        try {
            $user = User::find($id);

            if (!$user) {
                return $this->error('User not found.', 404);
            }

            $user->delete();

            return $this->success('User Deleted successfully', [], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'User delete failed');
        }
    }
    /**
     * Export Users to Excel (filters same as fetch API)
     * - Supports: search, role (and optional limit/offset if you want)
     * - Stores file in: storage/app/public/exports/users/{fileName}
     * - Returns public URL in response
     */
    public function exportExcel(Request $request)
    {
        try {
            // Same filters as fetch
            $limit  = $request->has('limit')  ? max(1, (int) $request->input('limit'))  : null; // optional
            $offset = $request->has('offset') ? max(0, (int) $request->input('offset')) : null; // optional
            $search = trim((string) $request->input('search', ''));
            $role   = trim((string) $request->input('role', ''));

            // Ensure directory exists
            $dir = storage_path('app/public/exports/users');
            if (!File::exists($dir)) {
                File::makeDirectory($dir, 0755, true);
            }

            // File name
            $fileName = 'users_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.xlsx';

            // Relative path for "public" disk
            $relativePath = 'exports/users/' . $fileName;

            // Store excel in public disk (=> storage/app/public/exports/users/..)
            Excel::store(
                new UsersExport($search, $role, $limit, $offset),
                $relativePath,
                'public'
            );

            // Public URL (requires: php artisan storage:link)
            $url = asset('storage/' . $relativePath);

            return $this->success('Excel exported successfully.', [
                'file_name' => $fileName,
                'url'       => $url,
            ], 200);

        } catch (\Throwable $e) {
            return $this->serverError($e, 'Users export failed');
        }
    }
}
