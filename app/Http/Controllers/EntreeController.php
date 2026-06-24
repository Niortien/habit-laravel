<?php

namespace App\Http\Controllers;

use App\Exceptions\NotFoundException;
use App\Http\Traits\ApiResponse;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\Entree;
use App\Models\Produit;
use App\Models\Variante;
use App\Services\StockMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EntreeController extends Controller
{
    use ApiResponse;

    public function __construct(private StockMovementService $movements) {}

    private function boutiqueId(Request $request): ?string
    {
        $user = $request->user();
        return $user->role === 'ADMIN' ? ($request->query('boutiqueId') ?? null) : $user->boutique_id;
    }

    public function index(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $q = Entree::with(['user', 'boutique', 'lignes.variante.produit'])->orderBy('created_at', 'desc');

        if ($boutiqueId)                  $q->where('boutique_id', $boutiqueId);
        if ($request->filled('fournisseur')) $q->where('fournisseur', 'like', '%'.$request->fournisseur.'%');
        if ($request->filled('dateDebut'))   $q->where('created_at', '>=', $request->dateDebut);
        if ($request->filled('dateFin'))     $q->where('created_at', '<=', $request->dateFin);

        $page  = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        $total = $q->count();
        $data  = $q->skip(($page - 1) * $limit)->take($limit)->get();

        return $this->paginated($data, $total, $page, $limit);
    }

    public function show(string $id): JsonResponse
    {
        $e = Entree::with(['user', 'boutique', 'lignes.variante.produit'])->find($id);
        if (!$e) throw new NotFoundException('Entrée introuvable', 'ENTREE_NOT_FOUND');
        return $this->success($e);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fournisseur'  => 'required|string',
            'notes'        => 'sometimes|nullable|string',
            'dateOperation'=> 'sometimes|nullable|date',
            'lignes'       => 'required|array|min:1',
            'lignes.*.varianteId'       => 'sometimes|nullable|uuid',
            'lignes.*.quantite'         => 'required|integer|min:1',
            'lignes.*.prixUnitaire'     => 'required|numeric|min:0',
            'lignes.*.newProduit'       => 'sometimes|nullable|array',
        ]);

        $boutiqueId = $this->boutiqueId($request);
        $userId     = $request->user()->id;
        $reference  = 'ENT-' . strtoupper(Str::random(8));
        $totalCout  = '0.00';

        $entree = DB::transaction(function () use ($data, $boutiqueId, $userId, $reference, &$totalCout) {
            $entree = Entree::create([
                'reference'   => $reference,
                'fournisseur' => $data['fournisseur'],
                'total_cout'  => '0.00',
                'notes'       => $data['notes'] ?? null,
                'user_id'     => $userId,
                'boutique_id' => $boutiqueId,
            ]);

            foreach ($data['lignes'] as $ligne) {
                $varianteId = $ligne['varianteId'] ?? null;

                if (!$varianteId && !empty($ligne['newProduit'])) {
                    $np = $ligne['newProduit'];
                    $produit = Produit::create([
                        'nom'          => $np['nom'],
                        'sku'          => Str::slug($np['nom']) . '-' . base_convert((string) time(), 10, 36),
                        'categorie_id' => $np['categorieId'],
                        'prix_vente'   => $np['prixVente'],
                        'prix_achat'   => $np['prixAchat'],
                        'image_url'    => $np['imageUrl'] ?? null,
                    ]);
                    $variante = Variante::create([
                        'produit_id'     => $produit->id,
                        'boutique_id'    => $boutiqueId,
                        'taille'         => $np['taille'],
                        'couleur'        => $np['couleur'],
                        'quantite_stock' => 0,
                        'seuil_alerte'   => $np['seuilAlerte'] ?? 5,
                    ]);
                    $varianteId = $variante->id;
                }

                $entree->lignes()->create([
                    'variante_id'   => $varianteId,
                    'quantite'      => $ligne['quantite'],
                    'prix_unitaire' => $ligne['prixUnitaire'],
                ]);

                $this->movements->create($varianteId, 'ENTREE', $ligne['quantite'], $userId, null, $reference);
                $totalCout = bcadd($totalCout, bcmul((string) $ligne['prixUnitaire'], (string) $ligne['quantite'], 2), 2);
            }

            $entree->update(['total_cout' => $totalCout]);
            return $entree;
        });

        return $this->success($entree->load(['lignes.variante.produit', 'user', 'boutique']), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $entree = Entree::find($id);
        if (!$entree) throw new NotFoundException('Entrée introuvable', 'ENTREE_NOT_FOUND');

        $data = $request->validate([
            'fournisseur' => 'sometimes|string',
            'notes'       => 'sometimes|nullable|string',
        ]);

        $entree->update($data);
        return $this->success($entree->fresh()->load(['lignes.variante.produit', 'user', 'boutique']));
    }

    public function destroy(string $id): JsonResponse
    {
        $entree = Entree::with('lignes')->find($id);
        if (!$entree) throw new NotFoundException('Entrée introuvable', 'ENTREE_NOT_FOUND');

        $userId = request()->user()->id;
        DB::transaction(function () use ($entree, $userId) {
            foreach ($entree->lignes as $ligne) {
                $this->movements->create($ligne->variante_id, 'SORTIE', $ligne->quantite, $userId, 'Annulation entrée ' . $entree->reference);
            }
            $entree->delete();
        });

        return $this->success($entree);
    }

    public function annuler(string $id): JsonResponse
    {
        $entree = Entree::with('lignes')->find($id);
        if (!$entree) throw new NotFoundException('Entrée introuvable', 'ENTREE_NOT_FOUND');

        if (str_starts_with($entree->fournisseur, '[ANNULÉE]')) {
            throw new \App\Exceptions\ConflictException('Entrée déjà annulée', 'ENTREE_ALREADY_CANCELLED');
        }

        $userId = request()->user()->id;
        DB::transaction(function () use ($entree, $userId) {
            foreach ($entree->lignes as $ligne) {
                $this->movements->create($ligne->variante_id, 'SORTIE', $ligne->quantite, $userId, 'Annulation entrée ' . $entree->reference);
            }
            $entree->update(['fournisseur' => '[ANNULÉE] ' . $entree->fournisseur]);
        });

        return $this->success($entree->fresh()->load(['lignes.variante.produit', 'user', 'boutique']));
    }
}
