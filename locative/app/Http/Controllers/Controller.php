<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Formats de réponse de l'API (consommés par le frontend, types ApiResponse) :
 * - liste   : { data, total, page, limit, totalPages }  → paginated()
 * - erreur  : { status: "error", message, ... }         → error()
 *   (identique au rendu des exceptions, voir bootstrap/app.php)
 */
abstract class Controller
{
    use AuthorizesRequests;

    /** Taille de page maximale acceptée (évite limit=100000). */
    protected const MAX_PAGE_SIZE = 100;

    /**
     * Pagine $query selon ?page= et ?limit= et renvoie le format de liste
     * commun. $resource : classe JsonResource à appliquer à chaque élément.
     */
    protected function paginated(Builder $query, Request $request, ?string $resource = null): JsonResponse
    {
        $limit = min(max((int) $request->get('limit', 10), 1), self::MAX_PAGE_SIZE);
        $page = max((int) $request->get('page', 1), 1);

        $total = (clone $query)->count();
        $items = $query->skip(($page - 1) * $limit)->take($limit)->get();

        return response()->json([
            'data' => $resource ? $resource::collection($items) : $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => max((int) ceil($total / $limit), 1),
        ]);
    }

    /**
     * Erreur métier au format commun. $extra : champs additionnels
     * (ex. 'code' lu par le frontend).
     */
    protected function error(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message] + $extra, $status);
    }
}
