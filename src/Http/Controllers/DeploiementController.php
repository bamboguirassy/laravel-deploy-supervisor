<?php

namespace Bamboguirassy\DeploySupervisor\Http\Controllers;

use Bamboguirassy\DeploySupervisor\Http\Requests\SearchDeploiementRequest;
use Bamboguirassy\DeploySupervisor\Http\Requests\TriggerDeploiementRequest;
use Bamboguirassy\DeploySupervisor\Http\Resources\DeploiementResource;
use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Bamboguirassy\DeploySupervisor\Services\DeploiementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * ⚠️ ATTENTION SÉCURITÉ — AUCUNE AUTORISATION N'EST APPLIQUÉE ICI.
 *
 * Ce contrôleur ne vérifie QUE l'authentification (via le middleware de
 * `config('deploy-supervisor.routes.middleware')`, ex. `auth:sanctum`) —
 * il n'impose aucune permission/rôle/Gate. Sans action de votre part,
 * N'IMPORTE QUEL utilisateur authentifié peut déclencher, consulter et
 * supprimer des déploiements.
 *
 * C'est volontaire : chaque application a sa propre logique d'autorisation
 * (rôles, permissions, is_admin...) que ce package ne peut pas deviner.
 * À VOUS d'ajouter la vôtre, typiquement :
 *   - en ajoutant votre middleware d'autorisation à
 *     `config('deploy-supervisor.routes.middleware')` (ex. un middleware
 *     `can:manage-deploy-supervisor`, ou le vôtre) ; ou
 *   - en désactivant `config('deploy-supervisor.routes.enabled')` et en
 *     déclarant vous-même ces routes dans votre application, dans le
 *     groupe de middlewares de votre choix (voir le README, section
 *     "Sécurité").
 */
class DeploiementController extends Controller
{
    public function __construct(private readonly DeploiementService $deploiementService) {}

    /**
     * Liste des environnements déployables, dérivée de
     * config('deploy-supervisor.targets') — jamais codée en dur, pour
     * accueillir n'importe quel nombre de cibles sans toucher au code.
     */
    public function environnements(Request $request): JsonResponse
    {
        $environnements = collect(config('deploy-supervisor.targets', []))
            ->map(fn ($cible, $code) => [
                'code' => $code,
                'label' => $cible['label'] ?? Str::headline($code),
            ])
            ->values();

        return response()->json(['success' => true, 'data' => $environnements]);
    }

    public function trigger(TriggerDeploiementRequest $request): JsonResponse
    {
        $deploiement = $this->deploiementService->trigger(
            $request->user(),
            $request->validated('cibles') ?? []
        );

        return response()->json([
            'success' => true,
            'message' => 'Déploiement démarré.',
            'data' => new DeploiementResource($deploiement),
        ], 202);
    }

    public function search(SearchDeploiementRequest $request): JsonResponse
    {
        $paginator = $this->deploiementService->search($request->validated())
            ->through(fn ($deploiement) => new DeploiementResource($deploiement));

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(Request $request, string $uid): JsonResponse
    {
        $deploiement = Deploiement::with('declenchePar')->where('uid', $uid)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new DeploiementResource($deploiement),
        ]);
    }

    public function destroy(Request $request, string $uid): JsonResponse
    {
        $deploiement = Deploiement::where('uid', $uid)->firstOrFail();

        $this->deploiementService->delete($deploiement);

        return response()->json(['success' => true, 'message' => 'Déploiement supprimé.']);
    }
}
