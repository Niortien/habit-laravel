<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Http\Traits\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(User::with('boutique')->orderBy('created_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'      => 'required|email',
            'password'   => 'required|string|min:8',
            'role'       => 'sometimes|in:ADMIN,VENDEUR',
            'boutiqueId' => 'sometimes|nullable|uuid|exists:boutiques,id',
        ]);

        if (User::where('email', $data['email'])->exists()) {
            throw new ConflictException('Un utilisateur avec cet email existe déjà', 'USER_EMAIL_EXISTS');
        }

        $user = User::create([
            'email'        => $data['email'],
            'password_hash' => Hash::make($data['password']),
            'role'         => $data['role'] ?? 'VENDEUR',
            'boutique_id'  => $data['boutiqueId'] ?? null,
        ]);

        return $this->success($user, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundException('Utilisateur introuvable', 'USER_NOT_FOUND');

        $data = $request->validate([
            'email'      => 'sometimes|email',
            'password'   => 'sometimes|string|min:8',
            'role'       => 'sometimes|in:ADMIN,VENDEUR',
            'boutiqueId' => 'sometimes|nullable|uuid',
        ]);

        $update = [];
        if (isset($data['email']))      $update['email']         = $data['email'];
        if (isset($data['role']))       $update['role']          = $data['role'];
        if (isset($data['boutiqueId'])) $update['boutique_id']   = $data['boutiqueId'];
        if (isset($data['password']))   $update['password_hash'] = Hash::make($data['password']);

        $user->update($update);
        return $this->success($user->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) throw new NotFoundException('Utilisateur introuvable', 'USER_NOT_FOUND');
        $user->delete();
        return $this->success($user);
    }
}
