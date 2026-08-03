<?php

namespace Bamboguirassy\DeploySupervisor\Console\Commands;

use Bamboguirassy\DeploySupervisor\Support\Webhook\WebhookUrlBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Génère un secret fort pour le webhook de déclenchement automatique et le
 * pré-remplit dans `.env` — même principe que `key:generate` ou les
 * commandes `vapid:generate` de certains packages notifications.
 */
class WebhookSecretCommand extends Command
{
    private const CLE_ENV = 'DEPLOY_SUPERVISOR_WEBHOOK_SECRET';

    protected $signature = 'deploy-supervisor:webhook-secret
        {--show : Afficher le secret généré sans écrire dans .env}
        {--force : Écraser une valeur existante dans .env sans confirmation}';

    protected $description = 'Génère un secret fort pour le webhook deploy-supervisor et le pré-remplit dans .env';

    public function handle(): int
    {
        $secret = bin2hex(random_bytes(32));

        if ($this->option('show')) {
            $this->line($secret);
            $this->afficherUrls($secret);

            return self::SUCCESS;
        }

        $envPath = $this->app->environmentFilePath();

        if (! File::exists($envPath)) {
            $this->error("Fichier .env introuvable ({$envPath}).");
            $this->line("Valeur générée (à ajouter vous-même) : <fg=yellow>{$secret}</>");

            return self::FAILURE;
        }

        $contenu = File::get($envPath);
        $valeurExistante = $this->valeurExistante($contenu);

        if ($valeurExistante !== null && $valeurExistante !== '' && ! $this->option('force')) {
            if (! $this->confirm(self::CLE_ENV . " est déjà défini dans .env. L'écraser ?")) {
                $this->line('Annulé.');

                return self::SUCCESS;
            }
        }

        File::put($envPath, $this->ecrireValeur($contenu, $secret));

        $this->info(self::CLE_ENV . ' généré et écrit dans .env.');
        $this->comment(
            'Renseignez cette même valeur côté fournisseur git (secret du webhook GitHub/GitLab, '
            . 'ou en query string ?secret=... pour Bitbucket) — voir le README, section "Webhook".'
        );
        $this->afficherUrls($secret);

        return self::SUCCESS;
    }

    private function afficherUrls(string $secret): void
    {
        $this->newLine();
        $this->line('URLs à configurer côté fournisseur (basées sur APP_URL) :');

        foreach (WebhookUrlBuilder::all($secret) as $provider => $url) {
            $this->line("  <fg=cyan;options=bold>{$provider}</> : {$url}");
        }
    }

    private function valeurExistante(string $contenu): ?string
    {
        if (! preg_match('/^' . self::CLE_ENV . '=(.*)$/m', $contenu, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }

    private function ecrireValeur(string $contenu, string $secret): string
    {
        $ligne = self::CLE_ENV . '=' . $secret;

        if (preg_match('/^' . self::CLE_ENV . '=.*$/m', $contenu)) {
            return preg_replace('/^' . self::CLE_ENV . '=.*$/m', $ligne, $contenu);
        }

        return rtrim($contenu) . "\n" . $ligne . "\n";
    }
}
