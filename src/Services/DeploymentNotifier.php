<?php

namespace Bamboguirassy\DeploySupervisor\Services;

use Bamboguirassy\DeploySupervisor\Mail\DeploiementDemarreMail;
use Bamboguirassy\DeploySupervisor\Mail\DeploiementTermineMail;
use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Notifications email au démarrage/à la fin d'un déploiement — même
 * philosophie que `DeploymentRunner::diffuser()` : jamais bloquant, jamais
 * fatal. Une erreur de configuration mail (ou un serveur SMTP injoignable)
 * ne doit jamais faire échouer le pipeline de déploiement lui-même.
 */
class DeploymentNotifier
{
    public function demarrage(Deploiement $deploiement): void
    {
        $this->envoyer(new DeploiementDemarreMail($deploiement));
    }

    public function fin(Deploiement $deploiement): void
    {
        $this->envoyer(new DeploiementTermineMail($deploiement));
    }

    private function envoyer(Mailable $mailable): void
    {
        if (! config('deploy-supervisor.notifications.mail.enabled', false)) {
            return;
        }

        $destinataires = config('deploy-supervisor.notifications.mail.to', []);

        if (empty($destinataires)) {
            return;
        }

        try {
            Mail::to($destinataires)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('Notification mail deploy-supervisor échouée', [
                'mailable' => get_class($mailable),
                'erreur' => $e->getMessage(),
            ]);
        }
    }
}
