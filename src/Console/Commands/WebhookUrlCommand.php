<?php

namespace Bamboguirassy\DeploySupervisor\Console\Commands;

use Bamboguirassy\DeploySupervisor\Support\Webhook\WebhookUrlBuilder;
use Illuminate\Console\Command;

/**
 * Affiche l'URL complète du webhook (basée sur APP_URL) pour chaque
 * fournisseur configuré — évite de la reconstruire à la main à partir de
 * `webhook.route.prefix` pour la coller dans GitHub/GitLab/Bitbucket.
 */
class WebhookUrlCommand extends Command
{
    protected $signature = 'deploy-supervisor:webhook-url';

    protected $description = "Affiche l'URL complète du webhook (basée sur APP_URL) pour chaque fournisseur";

    public function handle(): int
    {
        if (! config('deploy-supervisor.webhook.enabled', false)) {
            $this->comment(
                'DEPLOY_SUPERVISOR_WEBHOOK_ENABLED=false — ces URLs ne répondront rien tant que le webhook '
                . "n'est pas activé."
            );
            $this->newLine();
        }

        $secret = config('deploy-supervisor.webhook.secret');

        foreach (WebhookUrlBuilder::all($secret) as $provider => $url) {
            $this->line("<fg=cyan;options=bold>{$provider}</> : {$url}");
        }

        if (! $secret) {
            $this->newLine();
            $this->comment(
                'Aucun secret configuré (DEPLOY_SUPERVISOR_WEBHOOK_SECRET vide) — générez-en un avec '
                . 'php artisan deploy-supervisor:webhook-secret.'
            );
        }

        return self::SUCCESS;
    }
}
