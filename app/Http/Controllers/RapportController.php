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

    /**
     * @OA\Get(path="/rapports/ventes", tags={"Rapports"}, summary="Ventes groupées par période", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="groupBy", in="query", @OA\Schema(type="string", enum={"jour","semaine","mois"}, default="jour")),
     *     @OA\Parameter(name="dateDebut", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="dateFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="boutiqueId", in="query", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Ventes par période", @OA\JsonContent(ref="#/components/schemas/ApiResponse"))
     * )
     */
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

        $transactions = Transaction::selectRaw("DATE_FORMAT(created_at, '{$format}') as periode, SUM(montant) as totalVentes, COUNT(*) as nombreTransactions")
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->groupBy('periode')
            ->orderBy('periode')
            ->get()
            ->map(fn($row) => [
                'periode'           => $row->periode,
                'totalVentes'       => number_format((float) $row->totalVentes, 2, '.', ''),
                'nombreTransactions' => (int) $row->nombreTransactions,
                'nombreSorties'     => (int) $row->nombreTransactions,
            ]);

        return $this->success($transactions);
    }

    public function stockValeur(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $variantes  = Variante::with('produit')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->get();

        $valeurAchat     = '0.00';
        $valeurVente     = '0.00';
        $nombreVariantes = 0;
        $produitIds      = [];

        foreach ($variantes as $v) {
            if (!$v->produit) continue;
            $va = bcmul((string) $v->produit->prix_achat, (string) $v->quantite_stock, 2);
            $vv = bcmul((string) $v->produit->prix_vente, (string) $v->quantite_stock, 2);
            $valeurAchat = bcadd($valeurAchat, $va, 2);
            $valeurVente = bcadd($valeurVente, $vv, 2);
            $nombreVariantes++;
            $produitIds[$v->produit_id] = true;
        }

        return $this->success([
            'valeurTotaleAchat'  => $valeurAchat,
            'valeurTotaleVente'  => $valeurVente,
            'beneficePotentiel'  => bcsub($valeurVente, $valeurAchat, 2),
            'nombreVariantes'    => $nombreVariantes,
            'nombreProduits'     => count($produitIds),
        ]);
    }

    public function topProduits(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $dateDebut  = $request->get('dateDebut', now()->subDays(30)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        $top = MouvementStock::selectRaw('variante_id, SUM(ABS(quantite)) as totalVendu')
            ->where('type', 'SORTIE')
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('variante', fn($v) => $v->where('boutique_id', $boutiqueId)))
            ->groupBy('variante_id')
            ->orderByDesc('totalVendu')
            ->limit(10)
            ->with('variante.produit')
            ->get();

        $result = $top->filter(fn($r) => $r->variante && $r->variante->produit)
            ->map(fn($r) => [
                'produitId'      => $r->variante->produit->id,
                'nom'            => $r->variante->produit->nom,
                'sku'            => $r->variante->produit->sku,
                'quantiteTotale' => (int) $r->totalVendu,
                'montantTotal'   => number_format(
                    (float) $r->totalVendu * (float) $r->variante->produit->prix_vente, 2, '.', ''
                ),
            ])
            ->values();

        return $this->success($result);
    }

    public function fluxTresorerie(Request $request): JsonResponse
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

        $entreesRaw = Entree::selectRaw("DATE_FORMAT(created_at, '{$format}') as periode, SUM(total_cout) as total")
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->groupBy('periode')
            ->pluck('total', 'periode')
            ->toArray();

        $sortiesRaw = Transaction::selectRaw("DATE_FORMAT(created_at, '{$format}') as periode, SUM(montant) as total")
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->groupBy('periode')
            ->pluck('total', 'periode')
            ->toArray();

        $periodes = array_unique(array_merge(array_keys($entreesRaw), array_keys($sortiesRaw)));
        sort($periodes);

        $result = array_map(function ($periode) use ($entreesRaw, $sortiesRaw) {
            $e = (float) ($entreesRaw[$periode] ?? 0);
            $s = (float) ($sortiesRaw[$periode] ?? 0);
            return [
                'periode' => $periode,
                'entrees' => number_format($e, 2, '.', ''),
                'sorties' => number_format($s, 2, '.', ''),
                'solde'   => number_format($s - $e, 2, '.', ''),
            ];
        }, $periodes);

        return $this->success(array_values($result));
    }

    /**
     * @OA\Get(path="/rapports/resume-dashboard", tags={"Rapports"}, summary="Résumé tableau de bord", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="boutiqueId", in="query", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Dashboard",
     *         @OA\JsonContent(@OA\Property(property="data", type="object",
     *             @OA\Property(property="ventesAujourdhui", type="string"),
     *             @OA\Property(property="alertesStock", type="integer"),
     *             @OA\Property(property="produitsActifs", type="integer"),
     *             @OA\Property(property="topProduits", type="array", @OA\Items(type="object"))
     *         ), @OA\Property(property="meta", type="object", nullable=true), @OA\Property(property="timestamp", type="string", format="date-time"))
     *     )
     * )
     */
    public function resumeDashboard(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request);
        $dateDebut  = $request->get('dateDebut', now()->subDays(6)->toDateString());
        $dateFin    = $request->get('dateFin', now()->toDateString());

        // Ventes groupées par jour sur la période
        $ventesRows = Transaction::selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as periode, SUM(montant) as totalVentes, COUNT(*) as nombreTransactions")
            ->where('created_at', '>=', $dateDebut)
            ->where('created_at', '<=', $dateFin . ' 23:59:59')
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->groupBy('periode')
            ->orderBy('periode')
            ->get()
            ->map(fn($r) => [
                'periode'           => $r->periode,
                'totalVentes'       => number_format((float) $r->totalVentes, 2, '.', ''),
                'nombreSorties'     => (int) $r->nombreTransactions,
            ])
            ->values()
            ->toArray();

        // Top 5 produits — 7 derniers jours
        $topRaw = MouvementStock::selectRaw('variante_id, SUM(ABS(quantite)) as totalVendu')
            ->where('type', 'SORTIE')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->when($boutiqueId, fn($q) => $q->whereHas('variante', fn($v) => $v->where('boutique_id', $boutiqueId)))
            ->groupBy('variante_id')
            ->orderByDesc('totalVendu')
            ->limit(5)
            ->with('variante.produit')
            ->get();

        $topProduits = $topRaw->filter(fn($r) => $r->variante && $r->variante->produit)
            ->map(fn($r) => [
                'produitId'      => $r->variante->produit->id,
                'nom'            => $r->variante->produit->nom,
                'sku'            => $r->variante->produit->sku,
                'quantiteTotale' => (int) $r->totalVendu,
                'montantTotal'   => number_format(
                    (float) $r->totalVendu * (float) $r->variante->produit->prix_vente, 2, '.', ''
                ),
            ])
            ->values()
            ->toArray();

        // Diagnostic
        $totalProduits      = Produit::where('is_actif', true)->count();
        $totalVariantes     = Variante::when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))->count();
        $totalEntrees       = \App\Models\Entree::when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))->count();
        $totalVentesAllTime = (int) Transaction::when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))->count();
        $totalVentes7j      = (int) Transaction::where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->count();
        $sessionsOuvertes   = \App\Models\CaisseSession::where('statut', 'OUVERTE')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->count();

        return $this->success([
            'periode'     => ['debut' => $dateDebut, 'fin' => $dateFin],
            'ventes'      => $ventesRows,
            'topProduits' => $topProduits,
            'diagnostic'  => [
                'totalProduits'     => $totalProduits,
                'totalVariantes'    => $totalVariantes,
                'totalEntrees'      => $totalEntrees,
                'totalVentesAllTime' => $totalVentesAllTime,
                'totalVentes7j'     => $totalVentes7j,
                'sessionsOuvertes'  => $sessionsOuvertes,
                'hasData'           => $totalProduits > 0 || $totalEntrees > 0,
            ],
        ]);
    }

    /**
     * @OA\Get(path="/rapports/export-excel", tags={"Rapports"}, summary="Export des ventes en Excel (.xlsx)", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="dateDebut", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="dateFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="boutiqueId", in="query", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Fichier Excel", @OA\MediaType(mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"))
     * )
     */
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

    /**
     * @OA\Get(path="/rapports/export-pdf", tags={"Rapports"}, summary="Export des ventes en PDF", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="dateDebut", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="dateFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="boutiqueId", in="query", @OA\Schema(type="string", format="uuid")),
     *     @OA\Response(response=200, description="Fichier PDF", @OA\MediaType(mediaType="application/pdf"))
     * )
     */
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
