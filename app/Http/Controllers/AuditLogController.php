<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(path="/audit-logs", tags={"Audit"}, summary="Journal des actions sensibles (ADMIN)", security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="action", in="query", description="Un ou plusieurs codes séparés par des virgules", @OA\Schema(type="string")),
     *     @OA\Parameter(name="userId", in="query", @OA\Schema(type="string", format="uuid")),
     *     @OA\Parameter(name="search", in="query", description="Texte recherché dans la description", @OA\Schema(type="string")),
     *     @OA\Parameter(name="entityType", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="dateDebut", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="dateFin", in="query", @OA\Schema(type="string", format="date")),
     *     @OA\Response(response=200, description="Journal d'audit", @OA\JsonContent(ref="#/components/schemas/ApiResponse"))
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $q = AuditLog::with('user:id,email,role,boutique_id')->orderBy('created_at', 'desc');

        if ($request->filled('action'))     $q->whereIn('action', array_filter(explode(',', (string) $request->action)));
        if ($request->filled('entityType')) $q->where('entity_type', $request->entityType);
        if ($request->filled('userId'))     $q->where('user_id', $request->userId);
        if ($request->filled('search'))     $q->where('description', 'like', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $request->search) . '%');
        if ($request->filled('dateDebut'))  $q->where('created_at', '>=', $request->dateDebut);
        if ($request->filled('dateFin'))    $q->where('created_at', '<=', $request->dateFin);

        $page  = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 20)));
        $total = $q->count();
        $data  = $q->skip(($page - 1) * $limit)->take($limit)->get();

        return $this->paginated($data, $total, $page, $limit);
    }
}
