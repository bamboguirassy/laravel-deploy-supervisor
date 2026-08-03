<?php

namespace Bamboguirassy\DeploySupervisor\Mail;

use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Bamboguirassy\DeploySupervisor\Support\DeclencheParPresenter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class DeploiementTermineMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Deploiement $deploiement)
    {
        $this->onQueue(config('deploy-supervisor.queue', 'deploy'));
    }

    public function build(): self
    {
        $succes = $this->deploiement->statut === 'succes';

        return $this
            ->subject($succes ? '✅ Déploiement réussi' : '❌ Déploiement en échec')
            ->view('deploy-supervisor::mail.termine', [
                'deploiement' => $this->deploiement,
                'succes' => $succes,
                'cibles' => $this->ciblesDetail(),
                'declenchePar' => DeclencheParPresenter::format($this->deploiement->declenchePar),
                'dureeTotale' => $this->dureeTotale(),
                'lienDetail' => $this->lienDetail(),
            ]);
    }

    private function ciblesDetail(): array
    {
        $resultat = $this->deploiement->resultat ?? [];

        return collect($this->deploiement->cibles)
            ->map(function ($cible) use ($resultat) {
                return [
                    'code' => $cible,
                    'label' => config("deploy-supervisor.targets.{$cible}.label") ?? Str::headline($cible),
                    'statut' => $resultat[$cible]['statut'] ?? 'inconnu',
                    'erreur' => $resultat[$cible]['erreur'] ?? null,
                    'steps' => $resultat[$cible]['steps'] ?? [],
                ];
            })
            ->all();
    }

    private function dureeTotale(): ?string
    {
        if (! $this->deploiement->demarre_le || ! $this->deploiement->termine_le) {
            return null;
        }

        return $this->deploiement->demarre_le
            ->diff($this->deploiement->termine_le)
            ->cascade()
            ->forHumans(['short' => true]);
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
