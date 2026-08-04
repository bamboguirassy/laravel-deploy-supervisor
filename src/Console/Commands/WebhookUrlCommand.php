<?php

namespace Bamboguirassy\DeploySupervisor\Console\Commands;

use Bamboguirassy\DeploySupervisor\Support\Webhook\WebhookUrlBuilder;
use Illuminate\Console\Command;

/**
 * Affiche l'URL complète du webhook (basée sur APP_URL) pour chaque cible ×
 * fournisseur configuré — évite de la reconstruire à la main à partir de
 * `webhook.route.prefix` pour la coller dans GitHub/GitLab/Bitbucket. Chaque
 * cible a sa propre URL (`{prefix}/{provider}/{target}`), à renseigner côté
 * du dépôt git correspondant à cette cible.
 */
class WebhookUrlCommand extends Command
{
    protected $signature = 'deploy-supervisor:webhook-url {target? : Limiter l\'affichage à cette cible}';

    protected $description = "Affiche l'URL complète du webhook (basée sur APP_URL) pour chaque cible et fournisseur";

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
        $targetFiltre = $this->argument('target');

        if ($targetFiltre !== null && ! array_key_exists($targetFiltre, config('deploy-supervisor.targets', []))) {
            $this->error("Cible inconnue : {$targetFiltre}");

            return self::FAILURE;
        }

        $urlsParCible = $targetFiltre !== null
            ? [$targetFiltre => WebhookUrlBuilder::forTarget($targetFiltre, $secret)]
            : WebhookUrlBuilder::all($secret);

        if (empty($urlsParCible)) {
            $this->comment("Aucune cible configurée (config('deploy-supervisor.targets')).");

            return self::SUCCESS;
        }

        foreach ($urlsParCible as $target => $urls) {
            $this->line("<fg=yellow;options=bold>{$target}</>");

            foreach ($urls as $provider => $url) {
                $this->line("  <fg=cyan;options=bold>{$provider}</> : {$url}");
            }
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
