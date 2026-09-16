<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|min:8',
        ]);

        $authUser = User::where('phone', $request->phone)->first();

        if (!$authUser) {
            return response()->json([
                'success' => false,
                'field' => 'phone',
                'message' => 'Invalid phone.',
            ], 401);
        }

        if (!Hash::check($request->password, $authUser->password)) {
            return response()->json([
                'success' => false,
                'field' => 'password',
                'message' => 'Invalid password.',
            ], 401);
        }

        $token = $authUser->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $token,
            'user' => $authUser,
        ]);
    }
}
