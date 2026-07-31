<?php

namespace Bamboguirassy\DeploySupervisor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SearchDeploiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows(config('deploy-supervisor.gate', 'manage-deploy-supervisor'));
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
