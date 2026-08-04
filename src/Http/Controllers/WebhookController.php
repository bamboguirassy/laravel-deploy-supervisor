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
 * automatiquement le déploiement d'UNE cible sur push de la branche
 * configurée (`config('deploy-supervisor.branch')`) — sans authentification
 * utilisateur, l'authenticité est vérifiée par signature/token propre à
 * chaque fournisseur (voir `Support\Webhook\*`).
 *
 * La cible est portée par l'URL elle-même (`{prefix}/{provider}/{target}`,
 * voir `WebhookUrlBuilder`), pas déduite du dépôt émetteur du payload —
 * chaque dépôt (backend, frontend...) a donc sa propre URL de webhook à
 * configurer côté fournisseur, pointant vers sa cible.
 *
 * Désactivé par défaut (`config('deploy-supervisor.webhook.enabled')`) —
 * voir le README, section "Webhook", avant activation en production.
 */
class WebhookController extends Controller
{
    public function __construct(private readonly DeploiementService $deploiementService) {}

    public function handle(Request $request, string $provider, string $target): JsonResponse
    {
        $providerClass = config("deploy-supervisor.webhook.providers.{$provider}");

        abort_if(! $providerClass, 404, "Fournisseur webhook inconnu : {$provider}");
        abort_if(! array_key_exists($target, config('deploy-supervisor.targets', [])), 404, "Cible inconnue : {$target}");

        /** @var GitProviderWebhook $verifier */
        $verifier = app($providerClass);
        $secret = (string) config('deploy-supervisor.webhook.secret', '');

        if (! $verifier->verify($request, $secret)) {
            Log::warning('Webhook deploy-supervisor rejeté : signature/token invalide', [
                'provider' => $provider,
                'target' => $target,
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
        // événement (retry réseau) — un verrou de 30s sur le couple
        // (SHA, cible) évite un double déploiement de la même cible pour le
        // même commit. Sans SHA dans le payload, la déduplication est
        // simplement désactivée pour cet appel.
        if ($sha && ! Cache::lock("deploy-supervisor:webhook:{$sha}:{$target}", 30)->get()) {
            return response()->json([
                'success' => true,
                'message' => 'Déjà traité (déduplication).',
            ]);
        }

        $deploiement = $this->deploiementService->trigger(null, [$target]);

        return response()->json([
            'success' => true,
            'message' => "Déploiement de « {$target} » démarré via webhook.",
            'data' => new DeploiementResource($deploiement),
        ], 202);
    }
}
