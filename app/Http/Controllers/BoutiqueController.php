<?php

namespace App\Http\Controllers;

use App\Exceptions\NotFoundException;
use App\Http\Traits\ApiResponse;
use App\Models\Boutique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoutiqueController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(Boutique::orderBy('created_at')->get());
    }

    public function show(string $id): JsonResponse
    {
        $b = Boutique::find($id);
        if (!$b) throw new NotFoundException('Boutique introuvable', 'BOUTIQUE_NOT_FOUND');
        return $this->success($b);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'      => 'required|string',
            'adresse'  => 'sometimes|nullable|string',
            'ville'    => 'sometimes|nullable|string',
            'whatsapp' => 'sometimes|nullable|string',
        ]);
        return $this->success(Boutique::create($data), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $b = Boutique::find($id);
        if (!$b) throw new NotFoundException('Boutique introuvable', 'BOUTIQUE_NOT_FOUND');

        $data = $request->validate([
            'nom'      => 'sometimes|string',
            'adresse'  => 'sometimes|nullable|string',
            'ville'    => 'sometimes|nullable|string',
            'whatsapp' => 'sometimes|nullable|string',
        ]);
        $b->update($data);
        return $this->success($b->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        $b = Boutique::find($id);
        if (!$b) throw new NotFoundException('Boutique introuvable', 'BOUTIQUE_NOT_FOUND');
        $b->delete();
        return $this->success($b);
    }
}
