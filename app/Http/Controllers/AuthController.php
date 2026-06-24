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

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     tags={"Auth"},
     *     summary="Connexion utilisateur",
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email", example="admin@shop.com"),
     *             @OA\Property(property="password", type="string", example="StrongPass123!")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Connexion réussie",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="accessToken", type="string"),
     *                 @OA\Property(property="refreshToken", type="string"),
     *                 @OA\Property(property="user", ref="#/components/schemas/User")
     *             ),
     *             @OA\Property(property="meta", type="object", nullable=true),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Identifiants invalides", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/auth/refresh",
     *     tags={"Auth"},
     *     summary="Rafraîchir le token d'accès",
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(required={"refreshToken"},
     *             @OA\Property(property="refreshToken", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Nouveau accessToken",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="accessToken", type="string")
     *             ),
     *             @OA\Property(property="meta", type="object", nullable=true),
     *             @OA\Property(property="timestamp", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=409, description="Token invalide", @OA\JsonContent(ref="#/components/schemas/ErrorResponse"))
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     tags={"Auth"},
     *     summary="Déconnexion",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Déconnecté", @OA\JsonContent(ref="#/components/schemas/ApiResponse"))
     * )
     */
    public function logout(): JsonResponse
    {
        return $this->success(['success' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request->user());
    }
}
