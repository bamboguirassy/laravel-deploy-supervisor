<?php

namespace Bamboguirassy\DeploySupervisor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchDeploiementRequest extends FormRequest
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
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:demarre_le,statut'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
            'filters' => ['nullable', 'array'],
            'filters.statut' => ['nullable', 'string', 'in:en_cours,succes,echec'],
            'filters.cible' => ['nullable', 'string'],
        ];
    }
}
