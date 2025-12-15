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
                'success' => false,
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
            'success' => true,
            'message' => 'User registered successfully.',
            'data'    => $user,
        ], 201);
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
}
