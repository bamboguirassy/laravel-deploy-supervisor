<?php

namespace Bamboguirassy\DeploySupervisor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploiementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $formatter = config('deploy-supervisor.declenche_par_formatter');

        return [
            'uid' => $this->uid,
            'cibles' => $this->cibles,
            'statut' => $this->statut,
            'declenche_par' => $this->whenLoaded('declenchePar', fn () => $this->declenchePar && $formatter
                ? $formatter($this->declenchePar)
                : null
            ),
            'demarre_le' => $this->demarre_le?->toIso8601String(),
            'termine_le' => $this->termine_le?->toIso8601String(),
            'resultat' => $this->resultat,
        ];
    }
}
