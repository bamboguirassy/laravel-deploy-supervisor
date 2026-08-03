<?php

namespace Bamboguirassy\DeploySupervisor\Support\Webhook;

/**
 * Construit l'URL complète du webhook pour chaque fournisseur configuré, en
 * se basant sur `APP_URL` (via le helper `url()`, qui l'utilise comme base
 * en contexte console — pas de requête HTTP entrante à ce moment-là).
 */
class WebhookUrlBuilder
{
    /** @return array<string, string> code fournisseur => URL complète */
    public static function all(?string $secret = null): array
    {
        $prefix = config('deploy-supervisor.webhook.route.prefix', 'api/deploiement/webhook');
        $providers = array_keys(config('deploy-supervisor.webhook.providers', []));

        $urls = [];

        foreach ($providers as $provider) {
            $url = url(trim($prefix, '/') . '/' . $provider);

            // Bitbucket Cloud n'a pas de champ "secret" natif : le secret
            // est transmis en query string dans l'URL elle-même (voir
            // BitbucketWebhook::verify()).
            if ($provider === 'bitbucket' && $secret) {
                $url .= '?secret=' . $secret;
            }

            $urls[$provider] = $url;
        }

        return $urls;
    }
}
