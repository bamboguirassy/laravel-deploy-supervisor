<?php

namespace Bamboguirassy\DeploySupervisor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->uid,
            'cibles' => $this->cibles,
            'statut' => $this->statut,
            'declenche_par' => $this->whenLoaded(
                'declenchePar',
                fn () => $this->declenchePar ? $this->formaterDeclenchePar($this->declenchePar) : null
            ),
            'demarre_le' => $this->demarre_le?->toIso8601String(),
            'termine_le' => $this->termine_le?->toIso8601String(),
            'resultat' => $this->resultat,
        ];
    }

    /**
     * `declenche_par_formatter` est un nom de classe invokable (string),
     * PAS une closure (voir config('deploy-supervisor.declenche_par_formatter'))
     * — `config:cache` ne sait pas sérialiser une Closure. Résolue via le
     * conteneur pour bénéficier de l'injection de dépendances si besoin.
     */
    private function formaterDeclenchePar(mixed $user): ?array
    {
        $formatter = config('deploy-supervisor.declenche_par_formatter');

        if (! $formatter) {
            return null;
        }

        $resolu = is_string($formatter) ? app($formatter) : $formatter;

        return $resolu($user);
    }
}
