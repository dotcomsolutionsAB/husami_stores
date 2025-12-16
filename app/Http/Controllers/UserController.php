<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class UserController extends Controller
{
    //
    // Register API
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'username' => 'required|string|max:255|unique:users,username',
            'role'     => 'required|in:admin,user,sub-admin', // Enum values
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 200,
                'success' => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'username' => $request->username,
            'role'     => $request->role,
        ]);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'User registered successfully.',
            'data'    => $user,
        ], 200);
    }

    // fetch
    public function fetch(Request $request, $id = null)
    {
        try {
            // 🔹 SINGLE USER BY ID
            if ($id !== null) {
                $user = User::select('id','name','email','username','role','created_at')
                    ->find($id);

                if (! $user) {
                    return response()->json([
                        'status' => false,
                        'message' => 'User not found.',
                    ], 404);
                }

                return response()->json([
                    'code' => 200,
                    'status' => true,
                    'data' => $user,
                ], 200);
            }

            // 🔹 LIST USERS
            $limit  = (int) $request->input('limit', 10);
            $offset = (int) $request->input('offset', 0);
            $search = trim((string) $request->input('search', ''));

            $total = User::count();

            $q = User::select('id','name','email','username','role','created_at')
                ->orderBy('id','desc');

            if ($search !== '') {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
                });
            }

            $items = $q->skip($offset)->take($limit)->get();

            return response()->json([
                'code' => 200,
                'status' => true,
                'total' => $total,
                'count' => $items->count(),
                'data' => $items,
            ], 200);

        } catch (\Throwable $e) {
            Log::error('User fetch failed', ['error'=>$e->getMessage()]);
            return response()->json(['message'=>'Failed to fetch users'], 500);
        }
    }

    // update password
    public function updatePassword(Request $request)
    {
        try {
            // 1️⃣ Validate input
            $request->validate([
                'username' => ['required', 'string', 'exists:users,username'],
                'password' => ['required', 'string', 'min:8'],
            ]);

            // 2️⃣ Find user by username
            $user = User::where('username', $request->username)->first();

            if (! $user) {
                return response()->json([
                    'code'    => 404,
                    'status'  => false,
                    'message' => 'User not found.',
                ], 404);
            }

            // 3️⃣ Update password (ALWAYS hash)
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'code'    => 200,
                'status'  => true,
                'message' => 'Password updated successfully.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'code'    => 422,
                'status'  => false,
                'message' => 'Validation error!',
                'errors'  => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            Log::error('Password update failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json([
                'code'    => 500,
                'status'  => false,
                'message' => 'Something went wrong while updating password.',
            ], 500);
        }
    }

    // update
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'code' => 200,
                'success' => false,
                'message' => 'User not found.',
                'data' => [],
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'role'     => 'required|in:admin,user,sub-admin',

            // optional password update
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => 200,
                'success' => 'error',
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $updateData = [
            'name'     => $request->name,
            'email'    => $request->email,
            'username' => $request->username,
            'role'     => $request->role,
        ];

        // Update password only if provided
        if (!empty($request->password)) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ], 200);
    }

    public function delete($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'code' => 200,
                'success' => false,
                'message' => 'User not found.',
                'data' => [],
            ], 404);
        }

        $user->delete();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'User deleted successfully.',
            'data' => [],
        ], 200);
    }
}
