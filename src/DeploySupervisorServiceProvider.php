<?php

namespace Bamboguirassy\DeploySupervisor;

use Bamboguirassy\DeploySupervisor\Console\Commands\DeployRunCommand;
use Bamboguirassy\DeploySupervisor\Console\Commands\WebhookSecretCommand;
use Bamboguirassy\DeploySupervisor\Console\Commands\WebhookUrlCommand;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class DeploySupervisorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/deploy-supervisor.php', 'deploy-supervisor');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/deploy-supervisor.php' => config_path('deploy-supervisor.php'),
        ], 'deploy-supervisor-config');

        $this->publishes([
            __DIR__ . '/../database/migrations/create_deploy_supervisor_deploiements_table.php.stub'
                => database_path('migrations/' . date('Y_m_d_His') . '_create_deploy_supervisor_deploiements_table.php'),
        ], 'deploy-supervisor-migrations');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'deploy-supervisor');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/deploy-supervisor'),
        ], 'deploy-supervisor-views');

        if ($this->app->runningInConsole()) {
            $this->commands([DeployRunCommand::class, WebhookSecretCommand::class, WebhookUrlCommand::class]);
        }

        if (config('deploy-supervisor.routes.enabled', true)) {
            Route::prefix(config('deploy-supervisor.routes.prefix', 'api/deploiement'))
                ->middleware(config('deploy-supervisor.routes.middleware', ['api', 'auth:sanctum']))
                ->group(__DIR__ . '/../routes/api.php');
        }

        // Route webhook (GitHub/GitLab/Bitbucket) — désactivée par défaut,
        // volontairement SANS auth:sanctum (l'authenticité est vérifiée par
        // signature/token propre à chaque fournisseur, pas par session
        // utilisateur). Voir le README, section "Webhook".
        if (config('deploy-supervisor.webhook.enabled', false)) {
            Route::prefix(config('deploy-supervisor.webhook.route.prefix', 'api/deploiement/webhook'))
                ->middleware(config('deploy-supervisor.webhook.route.middleware', ['api']))
                ->group(__DIR__ . '/../routes/webhook.php');
        }

        // Canal privé de diffusion — accès refusé par défaut (échec fermé)
        // si la Gate configurée n'est pas définie côté application hôte.
        Broadcast::channel(config('deploy-supervisor.channel', 'deploy-supervisor'), function (Authenticatable $user) {
            return Gate::forUser($user)->allows(config('deploy-supervisor.gate', 'manage-deploy-supervisor'));
        });
    }
}
