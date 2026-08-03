<?php

namespace Bamboguirassy\DeploySupervisor\Support;

/**
 * Résout et applique `declenche_par_formatter` (nom de classe invokable,
 * jamais une closure — voir config('deploy-supervisor.declenche_par_formatter'))
 * pour formater l'utilisateur "déclenché par" — partagé entre
 * `DeploiementResource` (API) et les Mailables de notification, pour ne pas
 * dupliquer la logique de résolution.
 */
class DeclencheParPresenter
{
    public static function format(mixed $user): ?array
    {
        if (! $user) {
            return null;
        }

        $formatter = config('deploy-supervisor.declenche_par_formatter');

        if (! $formatter) {
            return null;
        }

        $resolu = is_string($formatter) ? app($formatter) : $formatter;

        return $resolu($user);
    }
}
