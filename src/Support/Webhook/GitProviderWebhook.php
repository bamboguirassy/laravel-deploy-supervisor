<?php

namespace Bamboguirassy\DeploySupervisor\Support\Webhook;

use Illuminate\Http\Request;

/**
 * Contrat commun à chaque fournisseur git supporté par le webhook de
 * déclenchement automatique — résolu par nom de classe via
 * `config('deploy-supervisor.webhook.providers')`, sur le même principe que
 * `declenche_par_formatter` : ajouter un fournisseur ne touche jamais au
 * code du package, seulement à cette config.
 */
interface GitProviderWebhook
{
    /**
     * Vérifie l'authenticité de la requête. Doit utiliser une comparaison à
     * temps constant (`hash_equals`) — jamais `===` sur un secret.
     */
    public function verify(Request $request, string $secret): bool;

    /**
     * Nom de la branche pushée, ou null si l'événement n'est pas un push de
     * branche pertinent (ex. push de tag) — dans ce cas l'appelant doit
     * ignorer la requête (répondre 200 sans déclencher de déploiement).
     */
    public function branch(Request $request): ?string;

    /**
     * SHA du commit pushé, utilisé pour la déduplication — null si absent
     * du payload (la déduplication est alors simplement désactivée pour cet
     * appel).
     */
    public function commitSha(Request $request): ?string;
}
