<?php

namespace Bamboguirassy\DeploySupervisor\Support\Webhook;

use Illuminate\Http\Request;

/**
 * Bitbucket Cloud ne signe pas nativement ses payloads (pas d'équivalent
 * X-Hub-Signature) : l'authenticité repose ici sur un secret partagé
 * transmis en query string dans l'URL du webhook configurée côté Bitbucket
 * (ex. https://.../webhook/bitbucket?secret=xxx), comparé à temps constant
 * au secret attendu.
 *
 * Format spécifique à Bitbucket Cloud (event "repo:push") ; Bitbucket
 * Server/Data Center a un format de payload différent, non couvert ici.
 */
class BitbucketWebhook implements GitProviderWebhook
{
    public function verify(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        return hash_equals($secret, (string) $request->query('secret', ''));
    }

    public function branch(Request $request): ?string
    {
        return $request->input('push.changes.0.new.name');
    }

    public function commitSha(Request $request): ?string
    {
        return $request->input('push.changes.0.new.target.hash');
    }
}
