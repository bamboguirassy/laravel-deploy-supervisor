<?php

namespace Bamboguirassy\DeploySupervisor\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Progression du pipeline de déploiement, diffusée en plusieurs petits
 * événements plutôt qu'un seul snapshot cumulatif du résultat complet :
 * un `resultat` cumulatif (avec la sortie console de chaque étape) dépasse
 * vite la limite de payload d'un serveur WebSocket (10 Ko par défaut sur
 * Reverb), qui rejette alors TOUS les broadcasts suivants en silence.
 *
 * `type` distingue le niveau de progression concerné :
 *   - 'etape'       : une commande démarre (statut=en_cours) ou se termine
 *                     (statut=succes|echec, exit_code, duration_ms)
 *   - 'cible'       : une cible est terminée (statut=succes|echec)
 *   - 'deploiement' : le pipeline entier est terminé
 *
 * Aucun payload ne contient jamais de sortie console — le détail complet
 * (avec output_tail) reste en base ; l'application consommatrice va le
 * chercher via son API le moment venu (ex. après l'événement 'deploiement').
 */
class DeploiementProgression implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public string $uid,
        public string $type,
        public ?string $cible = null,
        public ?string $label = null,
        public ?string $statut = null,
        public ?int $exitCode = null,
        public ?int $durationMs = null,
        public ?string $termineLe = null,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel(config('deploy-supervisor.channel', 'deploy-supervisor'))];
    }

    public function broadcastAs(): string
    {
        return "deploiement.{$this->type}";
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return array_filter([
            'uid' => $this->uid,
            'cible' => $this->cible,
            'label' => $this->label,
            'statut' => $this->statut,
            'exit_code' => $this->exitCode,
            'duration_ms' => $this->durationMs,
            'termine_le' => $this->termineLe,
        ], fn ($valeur) => $valeur !== null);
    }
}
