<?php

namespace Bamboguirassy\DeploySupervisor\Jobs;

use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Bamboguirassy\DeploySupervisor\Services\DeploymentRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunDeploiementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public Deploiement $deploiement)
    {
        $this->timeout = (int) config('deploy-supervisor.timeout', 900) + 60;
        $this->onQueue(config('deploy-supervisor.queue', 'deploy'));
    }

    public function handle(DeploymentRunner $runner): void
    {
        $runner->run($this->deploiement);
    }
}
