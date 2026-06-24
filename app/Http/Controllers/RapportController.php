<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Models\Entree;
use App\Models\MouvementStock;
use App\Models\Produit;
use App\Models\Sortie;
use App\Models\Transaction;
use App\Models\Variante;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RapportController extends Controller
{
    use ApiResponse;

    private function boutiqueId(Request $request): ?string
    {
        $user = $request->user();
        return $user->role === 'ADMIN' ? ($request->query('boutiqueId') ?? null) : $user->boutique_id;
    }

    public function ventes(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $groupBy    = $request->get('groupBy', 'jour');
        $dateDebut  = $request->get('dateDebut', now()->subDays(30)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        $format = match ($groupBy) {
            'semaine' => '%Y-%u',
            'mois'    => '%Y-%m',
            default   => '%Y-%m-%d',
        };

        $transactions = Transaction::selectRaw("DATE_FORMAT(created_at, '{$format}') as periode, SUM(montant) as total, COUNT(*) as count")
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->groupBy('periode')
            ->orderBy('periode')
            ->get();

        return $this->success($transactions);
    }

    public function stockValeur(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $variantes  = Variante::with('produit')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->get();

        $valeurAchat  = '0.00';
        $valeurVente  = '0.00';
        $totalUnites  = 0;
        $details      = [];

        foreach ($variantes as $v) {
            $va = bcmul((string) $v->produit->prix_achat, (string) $v->quantite_stock, 2);
            $vv = bcmul((string) $v->produit->prix_vente, (string) $v->quantite_stock, 2);
            $valeurAchat = bcadd($valeurAchat, $va, 2);
            $valeurVente = bcadd($valeurVente, $vv, 2);
            $totalUnites += $v->quantite_stock;
            $details[] = [
                'varianteId'    => $v->id,
                'produit'       => $v->produit->nom,
                'taille'        => $v->taille,
                'couleur'       => $v->couleur,
                'stock'         => $v->quantite_stock,
                'valeurAchat'   => $va,
                'valeurVente'   => $vv,
            ];
        }

        return $this->success([
            'valeurTotaleAchat'  => $valeurAchat,
            'valeurTotaleVente'  => $valeurVente,
            'beneficePotentiel'  => bcsub($valeurVente, $valeurAchat, 2),
            'totalUnites'        => $totalUnites,
            'details'            => $details,
        ]);
    }

    public function topProduits(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $dateDebut  = $request->get('dateDebut', now()->subDays(30)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        $top = MouvementStock::selectRaw('variante_id, SUM(quantite) as totalVendu')
            ->where('type', 'SORTIE')
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('variante', fn($v) => $v->where('boutique_id', $boutiqueId)))
            ->groupBy('variante_id')
            ->orderByDesc('totalVendu')
            ->limit(10)
            ->with('variante.produit')
            ->get();

        return $this->success($top);
    }

    public function fluxTresorerie(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $dateDebut  = $request->get('dateDebut', now()->subDays(30)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        $entrees = Entree::where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->sum('total_cout');

        $sorties = Transaction::where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->sum('montant');

        return $this->success([
            'totalEntrees'  => number_format((float) $entrees, 2, '.', ''),
            'totalSorties'  => number_format((float) $sorties, 2, '.', ''),
            'solde'         => number_format((float) $sorties - (float) $entrees, 2, '.', ''),
        ]);
    }

    public function resumeDashboard(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $today      = now()->startOfDay();

        $ventesToday = Transaction::where('created_at', '>=', $today)
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->sum('montant');

        $alertes = Variante::whereColumn('quantite_stock', '<=', 'seuil_alerte')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->count();

        $produitsActifs = Produit::where('is_actif', true)->count();

        $topProduits = MouvementStock::selectRaw('variante_id, SUM(quantite) as totalVendu')
            ->where('type', 'SORTIE')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('variante_id')
            ->orderByDesc('totalVendu')
            ->limit(5)
            ->with('variante.produit')
            ->get();

        return $this->success([
            'ventesAujourdhui'  => number_format((float) $ventesToday, 2, '.', ''),
            'alertesStock'      => $alertes,
            'produitsActifs'    => $produitsActifs,
            'topProduits'       => $topProduits,
        ]);
    }

    public function exportExcel(Request $request): mixed
    {
        $boutiqueId = $this->boutiqueId($request);
        $dateDebut  = $request->get('dateDebut', now()->subDays(30)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        $sorties = Sortie::with(['lignes.variante.produit', 'user'])
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->get();

        $rows = [['Référence', 'Type', 'Total', 'Date', 'Vendeur']];
        foreach ($sorties as $s) {
            $rows[] = [$s->reference, $s->type, $s->total_montant, $s->created_at->toDateTimeString(), $s->user->email];
        }

        $export = new class($rows) implements FromArray, WithHeadings {
            public function __construct(private array $rows) {}
            public function array(): array { return array_slice($this->rows, 1); }
            public function headings(): array { return $this->rows[0]; }
        };

        return Excel::download($export, 'rapport-ventes.xlsx');
    }

    public function exportPdf(Request $request): Response
    {
        $boutiqueId = $this->boutiqueId($request);
        $dateDebut  = $request->get('dateDebut', now()->subDays(30)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        $sorties = Sortie::with(['lignes.variante.produit', 'user'])
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->get();

        $pdf = Pdf::loadView('rapports.ventes', [
            'sorties'   => $sorties,
            'dateDebut' => $dateDebut,
            'dateFin'   => $dateFin,
        ]);

        return $pdf->download('rapport-ventes.pdf');
    }
}
