<?php

namespace Bamboguirassy\DeploySupervisor\Support\Webhook;

use Illuminate\Http\Request;

/**
 * GitLab n'utilise pas de signature HMAC : le secret configuré côté webhook
 * GitLab est renvoyé tel quel dans le header `X-Gitlab-Token`, à comparer
 * directement (à temps constant) au secret attendu.
 */
class GitlabWebhook implements GitProviderWebhook
{
    public function verify(Request $request, string $secret): bool
    {
        if ($secret === '') {
            return false;
        }

        return hash_equals($secret, (string) $request->header('X-Gitlab-Token', ''));
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
