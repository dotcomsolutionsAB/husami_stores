<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
}
