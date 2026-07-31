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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

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
        abort_unless(Gate::forUser($request->user())->allows(config('deploy-supervisor.gate', 'manage-deploy-supervisor')), 403);

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
        abort_unless(Gate::forUser($request->user())->allows(config('deploy-supervisor.gate', 'manage-deploy-supervisor')), 403);

        $deploiement = Deploiement::with('declenchePar')->where('uid', $uid)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => new DeploiementResource($deploiement),
        ]);
    }

    public function destroy(Request $request, string $uid): JsonResponse
    {
        abort_unless(Gate::forUser($request->user())->allows(config('deploy-supervisor.gate', 'manage-deploy-supervisor')), 403);

        $deploiement = Deploiement::where('uid', $uid)->firstOrFail();

        $this->deploiementService->delete($deploiement);

        return response()->json(['success' => true, 'message' => 'Déploiement supprimé.']);
    }
}
