<?php

namespace App\Http\Controllers\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    //
    // login
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required|string',
            ]);

            // Attempt login using the username column
            if (! Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
                return response()->json([
                    'code'    => 401,
                    'success' => false,
                    'message' => 'Invalid Username or Password.',
                ], 401);
            }

            $user  = Auth::user();
            $token = $user->createToken('API TOKEN')->plainTextToken; // requires Sanctum

            return response()->json([
                'code'    => 200,
                'success' => true,
                'message' => 'User logged in successfully!',
                'data' => [
                    'role'        => $user->role,
                    'token'       => $token,
                    'user_id'     => $user->id,
                    'name'        => $user->name,
                    'username'    => $user->username,
                    'email'       => $user->email,
                ],
            ], 200);

        } catch (\Throwable $e) {
            \Log::error('Login failed', [
                'user'  => $request->input('username'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'code'    => 500,
                'success' => false,
                'message' => 'Something went wrong.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // logout
    public function logout(Request $request)
    {
        // If using Passport or Sanctum, revoke the token
        $request->user()->tokens->each(function ($token) {
            $token->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out.',
        ], 200);
    }
}
