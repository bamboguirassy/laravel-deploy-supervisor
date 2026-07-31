<?php

namespace Bamboguirassy\DeploySupervisor\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class TriggerDeploiementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::forUser($this->user())->allows(config('deploy-supervisor.gate', 'manage-deploy-supervisor'));
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
