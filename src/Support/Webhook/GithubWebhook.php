<?php

namespace Bamboguirassy\DeploySupervisor\Support\Webhook;

use Illuminate\Http\Request;

/**
 * GitHub signe le corps brut de la requête avec HMAC-SHA256
 * (`X-Hub-Signature-256: sha256=...`), calculé à partir du secret configuré
 * côté webhook GitHub — jamais transmis dans le payload lui-même.
 */
class GithubWebhook implements GitProviderWebhook
{
    public function verify(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        $signature = $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $attendue = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($attendue, $signature);
    }

    public function branch(Request $request): ?string
    {
        $ref = (string) ($request->input('ref') ?? '');

        if (! str_starts_with($ref, 'refs/heads/')) {
            return null;
        }

        return substr($ref, strlen('refs/heads/'));
    }

    public function commitSha(Request $request): ?string
    {
        return $request->input('after') ?: null;
    }
}
