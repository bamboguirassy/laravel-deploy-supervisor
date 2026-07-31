<?php

namespace Bamboguirassy\DeploySupervisor\Services;

use Bamboguirassy\DeploySupervisor\Jobs\RunDeploiementJob;
use Bamboguirassy\DeploySupervisor\Models\Deploiement;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Couche métier/BDD du déploiement (crée la ligne, dispatch le job) —
 * distincte de `DeploymentRunner`, qui exécute réellement les commandes.
 */
class DeploiementService
{
    private const SORTABLE = ['demarre_le', 'statut'];

    public function trigger(?Authenticatable $user, array $cibles, bool $sync = false): Deploiement
    {
        $ciblesDisponibles = array_keys(config('deploy-supervisor.targets', []));

        $deploiement = Deploiement::create([
            'cibles' => empty($cibles) ? $ciblesDisponibles : array_values($cibles),
            'statut' => 'en_cours',
            'declenche_par_user_id' => $user?->getAuthIdentifier(),
            'demarre_le' => now(),
        ]);

        $sync ? RunDeploiementJob::dispatchSync($deploiement) : RunDeploiementJob::dispatch($deploiement);

        return $deploiement;
    }

    public function search(array $params): LengthAwarePaginator
    {
        $query = Deploiement::query()->with('declenchePar');

        $filters = $params['filters'] ?? [];

        if (! empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (! empty($filters['cible'])) {
            $query->whereJsonContains('cibles', $filters['cible']);
        }

        $sortBy = in_array($params['sort_by'] ?? null, self::SORTABLE, true)
            ? $params['sort_by']
            : 'demarre_le';
        $sortDir = ($params['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        // Départage par id : le lazy-loading ne doit jamais revoir deux fois
        // la même ligne quand plusieurs runs partagent la même seconde.
        $query->orderBy($sortBy, $sortDir)->orderBy('id', 'desc');

        $perPage = min((int) ($params['per_page'] ?? 20), 100);

        return $query->paginate($perPage, ['*'], 'page', $params['page'] ?? 1);
    }

    public function delete(Deploiement $deploiement): void
    {
        if ($deploiement->statut === 'en_cours') {
            abort(422, 'Impossible de supprimer un déploiement en cours.');
        }

        $deploiement->delete();
    }
}
