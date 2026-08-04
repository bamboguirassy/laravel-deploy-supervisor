<?php

namespace Bamboguirassy\DeploySupervisor\Support\Webhook;

/**
 * Construit l'URL complète du webhook (une par cible × fournisseur), en se
 * basant sur `APP_URL` (via le helper `url()`, qui l'utilise comme base en
 * contexte console — pas de requête HTTP entrante à ce moment-là).
 *
 * La cible fait partie du chemin (`{prefix}/{provider}/{target}`) : chaque
 * dépôt git (backend, frontend...) reçoit sa propre URL à configurer côté
 * fournisseur, pointant directement vers la cible à déployer — voir
 * `WebhookController`.
 */
class WebhookUrlBuilder
{
    /** @return array<string, string> code fournisseur => URL complète, pour une cible donnée */
    public static function forTarget(string $target, ?string $secret = null): array
    {
        $prefix = config('deploy-supervisor.webhook.route.prefix', 'api/deploiement/webhook');
        $providers = array_keys(config('deploy-supervisor.webhook.providers', []));

        $urls = [];

        foreach ($providers as $provider) {
            $url = url(trim($prefix, '/') . '/' . $provider . '/' . $target);

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

    /** @return array<string, array<string, string>> cible => (code fournisseur => URL complète) */
    public static function all(?string $secret = null): array
    {
        $targets = array_keys(config('deploy-supervisor.targets', []));

        return array_combine($targets, array_map(
            fn (string $target) => self::forTarget($target, $secret),
            $targets,
        ));
    }
}
