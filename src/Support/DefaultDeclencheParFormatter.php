<?php

namespace Bamboguirassy\DeploySupervisor\Support;

/**
 * Formateur par défaut de l'utilisateur "déclenché par" exposé par l'API.
 *
 * Une CLASSE invokable plutôt qu'une closure inline dans le fichier de
 * config : `php artisan config:cache` sérialise la config avec
 * `var_export()`, qui ne sait pas représenter une `Closure` ("Call to
 * undefined method Closure::__set_state()"). Un nom de classe est une
 * simple chaîne, donc sans souci de ce côté — résolue via le conteneur.
 *
 * Pour personnaliser (adapter aux colonnes de votre modèle User), créez
 * votre propre classe avec la même signature `__invoke($user): array` et
 * réglez `config('deploy-supervisor.declenche_par_formatter')` sur son nom.
 */
class DefaultDeclencheParFormatter
{
    public function __invoke(mixed $user): array
    {
        return [
            'id' => method_exists($user, 'getKey') ? $user->getKey() : null,
            'nom' => $user->name ?? $user->nom_complet ?? $user->email ?? (string) $user->getKey(),
        ];
    }
}
