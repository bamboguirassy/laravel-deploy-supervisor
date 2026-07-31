<?php

namespace Bamboguirassy\DeploySupervisor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Deploiement extends Model
{
    protected $fillable = [
        'uid', 'cibles', 'statut', 'declenche_par_user_id',
        'demarre_le', 'termine_le', 'resultat', 'log_path',
    ];

    protected $casts = [
        'cibles' => 'array',
        'resultat' => 'array',
        'demarre_le' => 'datetime',
        'termine_le' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('deploy-supervisor.table', 'deploy_supervisor_deploiements'));

        parent::__construct($attributes);
    }

    protected static function booted(): void
    {
        static::creating(function (self $deploiement) {
            $deploiement->uid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function declenchePar(): BelongsTo
    {
        return $this->belongsTo(config('deploy-supervisor.user_model', 'App\\Models\\User'), 'declenche_par_user_id');
    }
}
