<?php

namespace Bamboguirassy\DeploySupervisor\Console\Commands;

use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Bamboguirassy\DeploySupervisor\Services\DeploiementService;
use Illuminate\Console\Command;

/**
 * Déclenche le pipeline de déploiement via le MÊME code que les routes API
 * du package (DeploiementService::trigger + RunDeploiementJob) — même
 * historique, même suivi temps réel. Utile en secours si l'app est
 * inaccessible, ou pour un déploiement scripté (cron, CI).
 */
class DeployRunCommand extends Command
{
    protected $signature = 'deploy-supervisor:run
        {--cible=* : Cible à déployer — toutes par défaut (voir config(\'deploy-supervisor.targets\'))}
        {--user= : Identifiant de l\'utilisateur auquel attribuer le déploiement dans l\'historique}
        {--sync : Exécuter dans ce process (attend la fin) au lieu de passer par la queue}';

    protected $description = 'Déclenche un déploiement via le pipeline deploy-supervisor';

    public function handle(DeploiementService $deploiementService): int
    {
        $cibles = $this->option('cible');
        $disponibles = array_keys(config('deploy-supervisor.targets', []));

        foreach ($cibles as $cible) {
            if (! in_array($cible, $disponibles, true)) {
                $this->error("Cible invalide : {$cible} (attendu : " . implode(', ', $disponibles) . ')');

                return self::FAILURE;
            }
        }

        $user = null;
        if ($userId = $this->option('user')) {
            $userModel = config('deploy-supervisor.user_model', 'App\\Models\\User');
            $user = $userModel::find($userId);
            if (! $user) {
                $this->error("Utilisateur introuvable pour l'identifiant : {$userId}");

                return self::FAILURE;
            }
        }

        $sync = (bool) $this->option('sync');

        $this->info($sync
            ? 'Déploiement lancé en synchrone (ce process attend la fin du pipeline)…'
            : 'Déploiement mis en file d\'attente (queue "' . config('deploy-supervisor.queue', 'deploy') . '")…'
        );

        $deploiement = $deploiementService->trigger($user, $cibles, $sync);

        if (! $sync) {
            $this->info("Déploiement {$deploiement->uid} créé.");
            $this->comment(
                'Rappel : ceci ne s\'exécutera que si un worker (Horizon ou `queue:work`) '
                . 'écoute la queue "' . config('deploy-supervisor.queue', 'deploy') . '". '
                . 'Sans ça, le job reste en attente silencieuse — voir le README, section '
                . '"Prérequis : file d\'attente".'
            );

            return self::SUCCESS;
        }

        $deploiement->refresh();
        $this->afficherResultat($deploiement);

        return $deploiement->statut === 'succes' ? self::SUCCESS : self::FAILURE;
    }

    private function afficherResultat(Deploiement $deploiement): void
    {
        $this->newLine();
        $this->line("Statut global : <fg={$this->couleurStatut($deploiement->statut)}>{$deploiement->statut}</>");

        foreach ($deploiement->cibles as $cible) {
            $res = $deploiement->resultat[$cible] ?? null;
            if (! $res) {
                continue;
            }

            $this->newLine();
            $this->line("<options=bold>{$cible}</> — <fg={$this->couleurStatut($res['statut'])}>{$res['statut']}</>");

            if (! empty($res['erreur'])) {
                $this->error($res['erreur']);
                continue;
            }

            foreach ($res['steps'] ?? [] as $step) {
                $icone = match ($step['statut']) {
                    'succes' => '<fg=green>✔</>',
                    'echec' => '<fg=red>✘</>',
                    'ignore' => '<fg=gray>–</>',
                    default => '?',
                };
                $this->line("  {$icone} {$step['label']} ({$step['duration_ms']} ms)");
                if ($step['statut'] === 'echec' && ! empty($step['output_tail'])) {
                    $this->line("    <fg=red>{$step['output_tail']}</>");
                }
            }
        }
    }

    private function couleurStatut(string $statut): string
    {
        return match ($statut) {
            'succes' => 'green',
            'echec' => 'red',
            default => 'yellow',
        };
    }
}
