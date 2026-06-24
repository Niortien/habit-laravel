<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Exceptions\ValidationException;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            throw new ValidationException('Identifiants invalides', 'AUTH_INVALID_CREDENTIALS');
        }

        $accessToken  = JWTAuth::fromUser($user);
        $refreshToken = JWTAuth::fromUser($user, ['token_type' => 'refresh']);

        return $this->success([
            'accessToken'  => $accessToken,
            'refreshToken' => $refreshToken,
            'user'         => [
                'id'         => $user->id,
                'email'      => $user->email,
                'role'       => $user->role,
                'boutiqueId' => $user->boutique_id,
            ],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['refreshToken' => 'required|string']);

        try {
            $payload     = JWTAuth::setToken($request->refreshToken)->getPayload();
            $user        = User::findOrFail($payload->get('sub'));
            $accessToken = JWTAuth::fromUser($user);

            return $this->success(['accessToken' => $accessToken]);
        } catch (JWTException) {
            throw new ConflictException('Refresh token invalide', 'AUTH_INVALID_REFRESH');
        }
    }

    public function logout(): JsonResponse
    {
        return $this->success(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }
}
