<?php

namespace Bamboguirassy\DeploySupervisor\Mail;

use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Bamboguirassy\DeploySupervisor\Support\DeclencheParPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DeploiementDemarreMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Deploiement $deploiement)
    {
        $this->onQueue(config('deploy-supervisor.queue', 'deploy'));
    }

    public function build(): self
    {
        $cibles = collect($this->deploiement->cibles)
            ->map(fn ($cible) => config("deploy-supervisor.targets.{$cible}.label") ?? Str::headline($cible))
            ->implode(', ');

        return $this
            ->subject("🚀 Déploiement démarré — {$cibles}")
            ->view('deploy-supervisor::mail.demarre', [
                'deploiement' => $this->deploiement,
                'cibles' => $cibles,
                'declenchePar' => DeclencheParPresenter::format($this->deploiement->declenchePar),
                'lienDetail' => $this->lienDetail(),
            ]);
    }

    private function lienDetail(): ?string
    {
        $base = config('deploy-supervisor.notifications.mail.frontend_deploiement_page_url');

        if (! $base) {
            return null;
        }

        return rtrim($base, '/') . '/' . $this->deploiement->uid;
    }
}
