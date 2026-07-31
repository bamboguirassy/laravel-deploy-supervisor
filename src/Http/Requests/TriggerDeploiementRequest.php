<?php

namespace Bamboguirassy\DeploySupervisor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TriggerDeploiementRequest extends FormRequest
{
    /**
     * Aucune vérification de permission ici — seule l'authentification
     * compte (middleware de `config('deploy-supervisor.routes.middleware')`).
     * Voir l'avertissement de sécurité dans DeploiementController.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $cibles = array_keys(config('deploy-supervisor.targets', []));

        return [
            'cibles' => ['nullable', 'array'],
            'cibles.*' => ['string', 'in:' . implode(',', $cibles)],
        ];
    }
}
