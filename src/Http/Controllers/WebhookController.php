<?php

namespace Bamboguirassy\DeploySupervisor\Http\Controllers;

use Bamboguirassy\DeploySupervisor\Http\Resources\DeploiementResource;
use Bamboguirassy\DeploySupervisor\Services\DeploiementService;
use Bamboguirassy\DeploySupervisor\Support\Webhook\GitProviderWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Point d'entrée webhook (GitHub/GitLab/Bitbucket) pour déclencher
 * automatiquement un déploiement sur push de la branche configurée
 * (`config('deploy-supervisor.branch')`) — sans authentification
 * utilisateur, l'authenticité est vérifiée par signature/token propre à
 * chaque fournisseur (voir `Support\Webhook\*`).
 *
 * Désactivé par défaut (`config('deploy-supervisor.webhook.enabled')`) —
 * voir le README, section "Webhook", avant activation en production.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly DeploiementService $deploiementService) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerClass = config("deploy-supervisor.webhook.providers.{$provider}");

        abort_if(! $providerClass, 404, "Fournisseur webhook inconnu : {$provider}");

        /** @var GitProviderWebhook $verifier */
        $verifier = app($providerClass);
        $secret = (string) config('deploy-supervisor.webhook.secret', '');

        if (! $verifier->verify($request, $secret)) {
            Log::warning('Webhook deploy-supervisor rejeté : signature/token invalide', [
                'provider' => $provider,
            ]);

            abort(401, 'Signature webhook invalide.');
        }

        $branche = $verifier->branch($request);
        $brancheAttendue = config('deploy-supervisor.branch', 'main');

        if ($branche === null || $branche !== $brancheAttendue) {
            return response()->json([
                'success' => true,
                'message' => "Ignoré (branche « {$branche} » différente de « {$brancheAttendue} »).",
            ]);
        }

        $sha = $verifier->commitSha($request);

        // Déduplication : plusieurs fournisseurs peuvent renvoyer le même
        // événement (retry réseau) — un verrou de 30s sur le SHA évite un
        // double déploiement pour le même commit. Sans SHA dans le payload,
        // la déduplication est simplement désactivée pour cet appel.
        if ($sha && ! Cache::lock("deploy-supervisor:webhook:{$sha}", 30)->get()) {
            return response()->json([
                'success' => true,
                'message' => 'Déjà traité (déduplication).',
            ]);
        }

        $deploiement = $this->deploiementService->trigger(null, []);

        return response()->json([
            'success' => true,
            'message' => 'Déploiement démarré via webhook.',
            'data' => new DeploiementResource($deploiement),
        ], 202);
    }
}
