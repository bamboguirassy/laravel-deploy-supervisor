<?php

namespace Bamboguirassy\DeploySupervisor\Services;

use Bamboguirassy\DeploySupervisor\Events\DeploiementProgression;
use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Moteur d'exécution du pipeline de déploiement (distinct de
 * `DeploiementService`, qui ne s'occupe que de la couche BDD/HTTP) : exécute
 * les commandes shell déclarées dans `config('deploy-supervisor.targets')`,
 * étape par étape, et journalise/diffuse la progression au fil de l'eau.
 *
 * La BDD (via `sauvegarder()`) garde TOUJOURS le détail complet (output_tail
 * inclus). La diffusion temps réel (`diffuser()`) ne contient jamais de
 * sortie console — voir Events\DeploiementProgression.
 */
class DeploymentRunner
{
    private const OUTPUT_TAIL_LINES = 40;

    public function __construct(private readonly DeploymentNotifier $notifier) {}

    public function run(Deploiement $deploiement): void
    {
        $logPath = storage_path('logs/deploy-supervisor/' . $deploiement->uid . '.log');
        File::ensureDirectoryExists(dirname($logPath));
        $deploiement->log_path = $logPath;

        $this->notifier->demarrage($deploiement);

        $resultat = [];
        $globalStatut = 'succes';

        try {
            $this->executerCibles($deploiement, $resultat, $globalStatut, $logPath);
        } catch (\Throwable $e) {
            // Filet de sécurité de dernier recours : une exception hors de la
            // boucle par cible (ex. log illisible) ne doit pas non plus
            // laisser le Deploiement figé indéfiniment en "en_cours".
            Log::error('Déploiement interrompu par une exception globale', [
                'deploiement_uid' => $deploiement->uid,
                'erreur' => $e->getMessage(),
            ]);
            $globalStatut = 'echec';
        }

        $deploiement->statut = $globalStatut;
        $deploiement->termine_le = now();
        $this->sauvegarder($deploiement, $resultat);
        $this->diffuser(new DeploiementProgression(
            uid: $deploiement->uid,
            type: 'termine',
            statut: $globalStatut,
            termineLe: $deploiement->termine_le->toIso8601String(),
        ));

        $this->notifier->fin($deploiement);
    }

    private function executerCibles(Deploiement $deploiement, array &$resultat, string &$globalStatut, string $logPath): void
    {
        foreach ($deploiement->cibles as $cible) {
            $config = config("deploy-supervisor.targets.{$cible}");
            $path = $config['path'] ?? null;

            if (! $path || ! is_dir($path)) {
                $resultat[$cible] = [
                    'statut' => 'echec',
                    'erreur' => "Dossier introuvable : {$path}",
                    'steps' => [],
                ];
                $globalStatut = 'echec';
                $this->sauvegarder($deploiement, $resultat);
                $this->diffuser(new DeploiementProgression($deploiement->uid, 'cible', cible: $cible, statut: 'echec'));
                continue;
            }

            $resultat[$cible] = ['statut' => 'en_cours', 'steps' => []];
            $this->sauvegarder($deploiement, $resultat);

            $cibleEnEchec = false;

            try {
                foreach ($config['steps'] as $step) {
                    if ($cibleEnEchec) {
                        $resultat[$cible]['steps'][] = [
                            'label' => $step['label'],
                            'statut' => 'ignore',
                            'exit_code' => null,
                            'duration_ms' => 0,
                            'output_tail' => null,
                        ];
                        continue;
                    }

                    $command = $step['label'] === 'git pull'
                        ? $this->buildGitPullCommand($path, $step['command'])
                        : $step['command'];

                    // Étape "en cours" diffusée AVANT l'exécution : sans ça, une
                    // commande longue (build, install...) laisse le consommateur
                    // sans aucun signal pendant toute sa durée.
                    $resultat[$cible]['steps'][] = [
                        'label' => $step['label'],
                        'statut' => 'en_cours',
                        'exit_code' => null,
                        'duration_ms' => 0,
                        'output_tail' => null,
                    ];
                    $indexEtape = array_key_last($resultat[$cible]['steps']);
                    $this->sauvegarder($deploiement, $resultat);
                    $this->diffuser(new DeploiementProgression(
                        $deploiement->uid, 'etape', cible: $cible, label: $step['label'], statut: 'en_cours'
                    ));

                    $debut = microtime(true);
                    $result = Process::path($path)
                        ->timeout(config('deploy-supervisor.timeout', 900))
                        ->run($command);
                    $dureeMs = (int) ((microtime(true) - $debut) * 1000);

                    $this->appendLog($logPath, $cible, $step['label'], $result->output(), $result->errorOutput());

                    $ok = $result->successful();
                    $resultat[$cible]['steps'][$indexEtape] = [
                        'label' => $step['label'],
                        'statut' => $ok ? 'succes' : 'echec',
                        'exit_code' => $result->exitCode(),
                        'duration_ms' => $dureeMs,
                        'output_tail' => $this->tail($result->output() . $result->errorOutput()),
                    ];

                    if (! $ok) {
                        $cibleEnEchec = true;
                    }

                    $this->sauvegarder($deploiement, $resultat);
                    $this->diffuser(new DeploiementProgression(
                        $deploiement->uid, 'etape',
                        cible: $cible,
                        label: $step['label'],
                        statut: $ok ? 'succes' : 'echec',
                        exitCode: $result->exitCode(),
                        durationMs: $dureeMs,
                    ));
                }
            } catch (\Throwable $e) {
                // Ne JAMAIS laisser une exception (ex. ProcessTimedOutException
                // si une commande reste bloquée jusqu'au timeout) remonter
                // silencieusement : sans ce catch, le worker de queue meurt et
                // le Deploiement reste figé pour toujours à sa dernière étape
                // connue, sans qu'aucune erreur ne soit jamais diffusée.
                Log::error('Étape de déploiement interrompue par une exception', [
                    'deploiement_uid' => $deploiement->uid,
                    'cible' => $cible,
                    'erreur' => $e->getMessage(),
                ]);
                $resultat[$cible]['erreur'] = $e->getMessage();
                $cibleEnEchec = true;
            }

            $resultat[$cible]['statut'] = $cibleEnEchec ? 'echec' : 'succes';
            if ($cibleEnEchec) {
                $globalStatut = 'echec';
            }
            $this->sauvegarder($deploiement, $resultat);
            $this->diffuser(new DeploiementProgression(
                $deploiement->uid, 'cible', cible: $cible, statut: $cibleEnEchec ? 'echec' : 'succes'
            ));
        }
    }

    /**
     * Si DEPLOY_SUPERVISOR_GIT_USERNAME/DEPLOY_SUPERVISOR_GIT_TOKEN sont
     * renseignés et que le remote `origin` est en HTTPS, reconstruit la
     * commande avec les identifiants embarqués dans l'URL — sans jamais les
     * écrire dans .git/config. Retombe sur la commande par défaut (remote
     * `origin` tel quel, ex. SSH) si aucun identifiant n'est configuré ou si
     * le remote n'est pas HTTPS.
     */
    private function buildGitPullCommand(string $path, array $default): array
    {
        $username = config('deploy-supervisor.git.username');
        $token = config('deploy-supervisor.git.token');

        if (! $username || ! $token) {
            return $default;
        }

        $remote = Process::path($path)->run(['git', 'remote', 'get-url', 'origin']);
        $url = trim($remote->output());

        if (! $remote->successful() || ! str_starts_with($url, 'https://')) {
            return $default;
        }

        $urlAuthentifiee = 'https://' . rawurlencode($username) . ':' . rawurlencode($token)
            . '@' . substr($url, strlen('https://'));

        return ['git', 'pull', $urlAuthentifiee, config('deploy-supervisor.branch', 'main')];
    }

    private function sauvegarder(Deploiement $deploiement, array $resultat): void
    {
        $deploiement->resultat = $resultat;
        $deploiement->save();
    }

    private function diffuser(DeploiementProgression $event): void
    {
        // La diffusion temps réel est un confort d'UX, pas une garantie : si
        // le serveur de broadcasting est injoignable (ou rejette le
        // payload), le pipeline de déploiement (et son statut final en BDD)
        // ne doit surtout pas en dépendre.
        try {
            event($event);
        } catch (\Throwable $e) {
            Log::warning('Diffusion DeploiementProgression échouée', [
                'deploiement_uid' => $event->uid,
                'type' => $event->type,
                'erreur' => $e->getMessage(),
            ]);
        }
    }

    private function appendLog(string $logPath, string $cible, string $label, string $output, string $errorOutput): void
    {
        File::append($logPath, sprintf(
            "\n[%s] %s — %s\n%s%s",
            now()->toDateTimeString(),
            $cible,
            $label,
            $output,
            $errorOutput
        ));
    }

    private function tail(string $output, int $lines = self::OUTPUT_TAIL_LINES): string
    {
        $toutes = preg_split('/\r\n|\r|\n/', trim($output));

        return implode("\n", array_slice($toutes, -$lines));
    }
}
