<?php

namespace App\Http\Controllers;

use App\Exceptions\NotFoundException;
use App\Http\Traits\ApiResponse;
use App\Models\Boutique;
use App\Models\Categorie;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\ProduitImage;
use App\Models\Variante;
use App\Services\CloudinaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProduitController extends Controller
{
    use ApiResponse;

    public function __construct(private CloudinaryService $cloudinary) {}

    public function categories(): JsonResponse
    {
        return $this->success(Categorie::orderBy('nom')->get());
    }

    public function index(Request $request): JsonResponse
    {
        $q = Produit::with(['categorie', 'variantes', 'images'])->where('is_actif', true);

        if ($request->filled('categorieId')) $q->where('categorie_id', $request->categorieId);
        if ($request->filled('search'))       $q->where('nom', 'like', '%' . $request->search . '%');
        if ($request->filled('enPromo'))      $q->where('en_promo', filter_var($request->enPromo, FILTER_VALIDATE_BOOLEAN));
        if ($request->filled('boutiqueId')) {
            $q->whereHas('variantes', fn($v) => $v->where('boutique_id', $request->boutiqueId));
        }

        $page  = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        $total = $q->count();
        $data  = $q->skip(($page - 1) * $limit)->take($limit)->orderBy('created_at', 'desc')->get();

        return $this->paginated($data, $total, $page, $limit);
    }

    public function show(string $id): JsonResponse
    {
        $p = Produit::with(['categorie', 'variantes', 'images'])->find($id);
        if (!$p) throw new NotFoundException('Produit introuvable', 'PRODUIT_NOT_FOUND');
        return $this->success($p);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom'         => 'required|string',
            'sku'         => 'sometimes|nullable|string|unique:produits,sku',
            'description' => 'sometimes|nullable|string',
            'categorieId' => 'required|uuid|exists:categories,id',
            'prixVente'   => 'required|numeric|min:0',
            'prixAchat'   => 'required|numeric|min:0',
            'imageUrl'    => 'sometimes|nullable|string',
            'variantes'   => 'sometimes|array',
            'variantes.*.taille'         => 'required_with:variantes|string',
            'variantes.*.couleur'        => 'required_with:variantes|string',
            'variantes.*.quantiteStock'  => 'required_with:variantes|integer|min:0',
            'variantes.*.seuilAlerte'    => 'sometimes|integer|min:0',
        ]);

        $imageUrl = null;
        if (!empty($data['imageUrl']) && str_starts_with($data['imageUrl'], 'data:')) {
            $imageUrl = $this->cloudinary->uploadBase64($data['imageUrl']);
        } elseif (!empty($data['imageUrl'])) {
            $imageUrl = $data['imageUrl'];
        }

        $sku = $data['sku'] ?? (Str::slug($data['nom']) . '-' . base_convert((string) time(), 10, 36));

        $produit = Produit::create([
            'nom'          => $data['nom'],
            'sku'          => $sku,
            'description'  => $data['description'] ?? null,
            'categorie_id' => $data['categorieId'],
            'prix_vente'   => $data['prixVente'],
            'prix_achat'   => $data['prixAchat'],
            'image_url'    => $imageUrl,
        ]);

        if (!empty($data['variantes'])) {
            $boutiques = Boutique::pluck('id')->toArray();
            foreach ($data['variantes'] as $v) {
                foreach ($boutiques as $boutiqueId) {
                    Variante::create([
                        'produit_id'     => $produit->id,
                        'boutique_id'    => $boutiqueId,
                        'taille'         => $v['taille'],
                        'couleur'        => $v['couleur'],
                        'quantite_stock' => $v['quantiteStock'],
                        'seuil_alerte'   => $v['seuilAlerte'] ?? 5,
                    ]);
                }
                if (empty($boutiques)) {
                    Variante::create([
                        'produit_id'     => $produit->id,
                        'taille'         => $v['taille'],
                        'couleur'        => $v['couleur'],
                        'quantite_stock' => $v['quantiteStock'],
                        'seuil_alerte'   => $v['seuilAlerte'] ?? 5,
                    ]);
                }
            }
        }

        return $this->success($produit->load(['categorie', 'variantes', 'images']), 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $produit = Produit::find($id);
        if (!$produit) throw new NotFoundException('Produit introuvable', 'PRODUIT_NOT_FOUND');

        $data = $request->validate([
            'nom'            => 'sometimes|string',
            'description'    => 'sometimes|nullable|string',
            'prixVente'      => 'sometimes|numeric|min:0',
            'prixAchat'      => 'sometimes|numeric|min:0',
            'imageUrl'       => 'sometimes|nullable|string',
            'isActif'        => 'sometimes|boolean',
            'enPromo'        => 'sometimes|boolean',
            'prixPromo'      => 'sometimes|nullable|numeric|min:0',
            'dateDebutPromo' => 'sometimes|nullable|date',
            'dateFinPromo'   => 'sometimes|nullable|date',
        ]);

        $map = [
            'nom' => 'nom', 'description' => 'description', 'prixVente' => 'prix_vente',
            'prixAchat' => 'prix_achat', 'imageUrl' => 'image_url', 'isActif' => 'is_actif',
            'enPromo' => 'en_promo', 'prixPromo' => 'prix_promo',
            'dateDebutPromo' => 'date_debut_promo', 'dateFinPromo' => 'date_fin_promo',
        ];

        $update = [];
        foreach ($map as $from => $to) {
            if (array_key_exists($from, $data)) $update[$to] = $data[$from];
        }

        $produit->update($update);
        return $this->success($produit->fresh()->load(['categorie', 'variantes', 'images']));
    }

    public function destroy(string $id): JsonResponse
    {
        $produit = Produit::find($id);
        if (!$produit) throw new NotFoundException('Produit introuvable', 'PRODUIT_NOT_FOUND');
        $produit->update(['is_actif' => false]);
        return $this->success($produit);
    }

    public function addImage(Request $request, string $id): JsonResponse
    {
        $produit = Produit::find($id);
        if (!$produit) throw new NotFoundException('Produit introuvable', 'PRODUIT_NOT_FOUND');

        $request->validate(['file' => 'required|image|max:10240']);
        $url = $this->cloudinary->uploadFile($request->file('file')->getRealPath());
        $ordre = ProduitImage::where('produit_id', $id)->max('ordre') + 1;
        $image = ProduitImage::create(['produit_id' => $id, 'url' => $url, 'ordre' => $ordre]);

        return $this->success($image, 201);
    }

    public function removeImage(string $id, string $imageId): JsonResponse
    {
        $image = ProduitImage::where('id', $imageId)->where('produit_id', $id)->first();
        if (!$image) throw new NotFoundException('Image introuvable', 'IMAGE_NOT_FOUND');
        $this->cloudinary->deleteByUrl($image->url);
        $image->delete();
        return $this->success($image);
    }

    public function mouvements(Request $request, string $id): JsonResponse
    {
        $produit = Produit::find($id);
        if (!$produit) throw new NotFoundException('Produit introuvable', 'PRODUIT_NOT_FOUND');

        $varianteIds = $produit->variantes()->pluck('id');
        $page  = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));

        $q     = MouvementStock::with(['variante', 'user'])->whereIn('variante_id', $varianteIds)->orderBy('created_at', 'desc');
        $total = $q->count();
        $data  = $q->skip(($page - 1) * $limit)->take($limit)->get();

        return $this->paginated($data, $total, $page, $limit);
    }
}
