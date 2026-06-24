<?php

namespace App\Http\Controllers;

use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Http\Traits\ApiResponse;
use App\Models\CaisseSession;
use App\Models\Entree;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaisseController extends Controller
{
    use ApiResponse;

    private function boutiqueId(Request $request, ?string $queryBoutiqueId = null): ?string
    {
        $user = $request->user();
        return $user->role === 'ADMIN' ? $queryBoutiqueId : $user->boutique_id;
    }

    public function listSessions(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request, $request->query('boutiqueId'));
        $q = CaisseSession::with(['user', 'boutique'])->orderBy('date_ouverture', 'desc');
        if ($boutiqueId)                   $q->where('boutique_id', $boutiqueId);
        if ($request->filled('dateDebut')) $q->where('date_ouverture', '>=', $request->dateDebut);
        if ($request->filled('dateFin'))   $q->where('date_ouverture', '<=', $request->dateFin);

        $page  = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        $total = $q->count();
        $data  = $q->skip(($page - 1) * $limit)->take($limit)->get();

        return $this->paginated($data, $total, $page, $limit);
    }

    public function activeSession(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request, $request->query('boutiqueId'));
        $session = CaisseSession::with(['user', 'boutique'])
            ->where('statut', 'OUVERTE')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->orderBy('date_ouverture', 'desc')
            ->first();

        return $this->success($session);
    }

    public function openSession(Request $request): JsonResponse
    {
        $data = $request->validate([
            'montantOuverture' => 'sometimes|nullable|numeric|min:0',
        ]);

        $boutiqueId = $this->boutiqueId($request, $request->query('boutiqueId'));
        $userId     = $request->user()->id;

        $active = CaisseSession::where('statut', 'OUVERTE')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->first();

        if ($active) {
            throw new ConflictException('Une session est déjà ouverte', 'SESSION_ALREADY_OPEN', ['sessionId' => $active->id]);
        }

        $session = CaisseSession::create([
            'user_id'           => $userId,
            'boutique_id'       => $boutiqueId,
            'date_ouverture'    => now(),
            'montant_ouverture' => $data['montantOuverture'] ?? '0',
            'statut'            => 'OUVERTE',
        ]);

        return $this->success($session->load(['user', 'boutique']), 201);
    }

    public function closeSession(Request $request, string $id): JsonResponse
    {
        $session = CaisseSession::with('transactions')->find($id);
        if (!$session) throw new NotFoundException('Session introuvable', 'SESSION_NOT_FOUND');
        if ($session->statut === 'FERMEE') {
            throw new ConflictException('La fermeture de caisse est irréversible', 'SESSION_CLOSED');
        }

        $data = $request->validate(['montantFermeture' => 'required|numeric|min:0']);

        $session->update([
            'statut'            => 'FERMEE',
            'date_fermeture'    => now(),
            'montant_fermeture' => $data['montantFermeture'],
        ]);

        return $this->success($session->fresh()->load('transactions'));
    }

    public function listTransactions(Request $request, string $id): JsonResponse
    {
        $q = Transaction::where('session_id', $id)->orderBy('created_at', 'desc');

        if ($request->filled('modePaiement')) $q->where('mode_paiement', $request->modePaiement);
        if ($request->filled('dateDebut'))    $q->where('created_at', '>=', $request->dateDebut);
        if ($request->filled('dateFin'))      $q->where('created_at', '<=', $request->dateFin);

        $page  = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        $total = $q->count();
        $data  = $q->skip(($page - 1) * $limit)->take($limit)->get();

        return $this->paginated($data, $total, $page, $limit);
    }

    public function createTransaction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'montant'      => 'required|numeric|min:0',
            'modePaiement' => 'required|in:CASH,WAVE,ORANGE_MONEY,CARTE,MTN_MONEY',
            'sortieId'     => 'sometimes|nullable|uuid',
            'reference'    => 'sometimes|nullable|string',
            'notes'        => 'sometimes|nullable|string',
        ]);

        $boutiqueId = $this->boutiqueId($request, $request->query('boutiqueId'));

        $active = CaisseSession::where('statut', 'OUVERTE')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->orderBy('date_ouverture', 'desc')
            ->first();

        if (!$active) {
            throw new ConflictException('Aucune session de caisse ouverte', 'NO_ACTIVE_SESSION');
        }

        $transaction = Transaction::create([
            'session_id'    => $active->id,
            'sortie_id'     => $data['sortieId'] ?? null,
            'montant'       => $data['montant'],
            'mode_paiement' => $data['modePaiement'],
            'reference'     => $data['reference'] ?? null,
            'notes'         => $data['notes'] ?? null,
        ]);

        return $this->success($transaction, 201);
    }

    public function resumeJour(Request $request): JsonResponse
    {
        $boutiqueId = $this->boutiqueId($request, $request->query('boutiqueId'));
        $todayStart = now()->startOfDay();

        $transactions = Transaction::where('created_at', '>=', $todayStart)
            ->when($boutiqueId, fn($q) => $q->whereHas('session', fn($s) => $s->where('boutique_id', $boutiqueId)))
            ->get();

        $achats = Entree::where('created_at', '>=', $todayStart)
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->get();

        $session = CaisseSession::where('statut', 'OUVERTE')
            ->when($boutiqueId, fn($q) => $q->where('boutique_id', $boutiqueId))
            ->orderBy('date_ouverture', 'desc')
            ->first();

        $totalVentes  = $transactions->reduce(fn($c, $t) => bcadd($c, (string) $t->montant, 2), '0.00');
        $totalAchats  = $achats->reduce(fn($c, $e) => bcadd($c, (string) $e->total_cout, 2), '0.00');
        $beneficeNet  = bcsub($totalVentes, $totalAchats, 2);

        $parModePaiement = [];
        foreach ($transactions as $tx) {
            $parModePaiement[$tx->mode_paiement] = bcadd(
                $parModePaiement[$tx->mode_paiement] ?? '0.00',
                (string) $tx->montant, 2
            );
        }

        return $this->success([
            'session' => $session ? [
                'id'               => $session->id,
                'statut'           => $session->statut,
                'montantOuverture' => (string) $session->montant_ouverture,
                'dateOuverture'    => $session->date_ouverture->toISOString(),
            ] : null,
            'totalVentes'       => $totalVentes,
            'totalTransactions' => $transactions->count(),
            'totalAchats'       => $totalAchats,
            'beneficeNet'       => $beneficeNet,
            'parModePaiement'   => $parModePaiement,
        ]);
    }
}
