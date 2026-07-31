<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    |
    | Nom de la table où sont stockés les déploiements (historique + suivi).
    | Changez-la si "deploy_supervisor_deploiements" entre en conflit avec
    | une table déjà existante dans votre projet.
    */
    'table' => env('DEPLOY_SUPERVISOR_TABLE', 'deploy_supervisor_deploiements'),

    /*
    |--------------------------------------------------------------------------
    | Modèle utilisateur
    |--------------------------------------------------------------------------
    |
    | Modèle utilisé pour "déclenché par". Doit avoir une clé primaire
    | standard ; le formatage affiché (nom, etc.) est personnalisable via
    | `declenche_par_formatter` ci-dessous.
    */
    'user_model' => env('DEPLOY_SUPERVISOR_USER_MODEL', 'App\\Models\\User'),

    /*
    | Formate l'utilisateur qui a déclenché un déploiement pour l'API —
    | nom d'une classe invokable (`__invoke($user): array`), PAS une closure :
    | `php artisan config:cache` sérialise la config avec var_export(), qui
    | ne sait pas représenter une Closure ("Call to undefined method
    | Closure::__set_state()"). Un nom de classe est une simple chaîne, donc
    | sans souci — résolue via le conteneur.
    |
    | Pour personnaliser (adapter aux colonnes de votre modèle User), créez
    | votre propre classe avec la même signature et réglez cette valeur sur
    | son nom, ex. 'declenche_par_formatter' => \App\Support\MonFormatter::class.
    */
    'declenche_par_formatter' => \Bamboguirassy\DeploySupervisor\Support\DefaultDeclencheParFormatter::class,

    /*
    |--------------------------------------------------------------------------
    | Autorisation
    |--------------------------------------------------------------------------
    |
    | ⚠️ Ne protège PLUS les routes HTTP (voir "Routes API" ci-dessous) —
    | uniquement le canal de diffusion temps réel, qui a besoin d'un
    | callback booléen quoi qu'il arrive. Nom de la Gate à définir dans
    | VOTRE application (ex. dans un ServiceProvider :
    | Gate::define('manage-deploy-supervisor', fn ($user) => $user->is_admin)).
    | Non définie = canal refusé par défaut (échec fermé).
    */
    'gate' => env('DEPLOY_SUPERVISOR_GATE', 'manage-deploy-supervisor'),

    /*
    |--------------------------------------------------------------------------
    | Routes API
    |--------------------------------------------------------------------------
    |
    | Le package enregistre ses propres routes (POST /deploiement,
    | POST /deploiement/search, GET /deploiement/environnements,
    | GET|DELETE /deploiement/{uid}).
    |
    | ⚠️ ATTENTION SÉCURITÉ : ces routes ne portent AUCUNE vérification de
    | permission/rôle — seul `middleware` ci-dessous s'applique (par défaut,
    | juste l'authentification via `auth:sanctum`). C'est volontaire : ce
    | package ne peut pas deviner votre logique d'autorisation (rôles,
    | permissions, is_admin...). AJOUTEZ VOTRE PROPRE MIDDLEWARE
    | D'AUTORISATION à ce tableau avant d'exposer ce module en production —
    | sans ça, n'importe quel utilisateur authentifié peut déclencher,
    | consulter et supprimer des déploiements. Voir le README, section
    | "Sécurité".
    |
    | Désactivez `enabled` si vous préférez déclarer vous-même ces routes
    | (dans le groupe de middlewares de votre choix) en pointant vers
    | Bamboguirassy\DeploySupervisor\Http\Controllers\DeploiementController.
    */
    'routes' => [
        'enabled' => env('DEPLOY_SUPERVISOR_ROUTES', true),
        'prefix' => env('DEPLOY_SUPERVISOR_ROUTE_PREFIX', 'api/deploiement'),
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Diffusion temps réel
    |--------------------------------------------------------------------------
    |
    | Canal privé de diffusion de la progression (Reverb, Pusher, ou tout
    | driver broadcasting compatible). Chaque événement est volontairement
    | minuscule (jamais de sortie console dedans) pour rester bien en
    | dessous des limites de payload des serveurs WebSocket (ex. 10 Ko par
    | défaut sur Reverb) — le détail complet (avec output_tail) reste
    | toujours en base, à récupérer via l'API le moment venu.
    */
    'channel' => env('DEPLOY_SUPERVISOR_CHANNEL', 'deploy-supervisor'),

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Queue dédiée pour le job d'exécution du pipeline — isolez-la des
    | autres queues (un déploiement peut prendre plusieurs minutes).
    |
    | ⚠️ Cette queue doit être ÉCOUTÉE par un worker pour que quoi que ce
    | soit se passe réellement. Avec Horizon, ajoutez-la à la liste `queue`
    | d'un supervisor dans config/horizon.php :
    |
    |     'supervisor-deploy' => [
    |         'connection' => 'redis',
    |         'queue' => ['deploy'],
    |         'balance' => 'simple',
    |         'maxProcesses' => 1,
    |     ],
    |
    | Sans worker sur cette queue, POST /deploiement répond 202 mais RIEN ne
    | se passe jamais — le job reste en attente silencieuse dans Redis, sans
    | erreur visible. Voir le README, section "Prérequis : file d'attente".
    */
    'queue' => env('DEPLOY_SUPERVISOR_QUEUE', 'deploy'),

    'branch' => env('DEPLOY_SUPERVISOR_GIT_BRANCH', 'main'),

    'timeout' => (int) env('DEPLOY_SUPERVISOR_TIMEOUT', 900),

    /*
    | Identifiants git optionnels pour l'étape "git pull" : si renseignés,
    | et que le remote `origin` de la cible est en HTTPS, le pipeline
    | reconstruit l'URL avec ces identifiants embarqués — uniquement pour
    | la commande de ce run, jamais écrits dans .git/config. Utile quand le
    | process qui déploie (ex. un worker de queue sans session interactive)
    | n'a pas accès à l'agent SSH / au credential helper git de
    | l'utilisateur ayant cloné le dépôt manuellement.
    |
    | DEPLOY_SUPERVISOR_GIT_TOKEN doit être un Personal Access Token (les
    | plateformes git n'acceptent plus le mot de passe du compte pour les
    | opérations git), avec le scope minimal lecture seule sur le dépôt.
    */
    'git' => [
        'username' => env('DEPLOY_SUPERVISOR_GIT_USERNAME'),
        'token' => env('DEPLOY_SUPERVISOR_GIT_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cibles
    |--------------------------------------------------------------------------
    |
    | Chaque cible déclare un `label` optionnel (affiché tel quel côté
    | frontend — sinon dérivé automatiquement du code, ex. "backend" ->
    | "Backend"), un dossier (`path`) et une liste ORDONNÉE d'étapes
    | (`label` + `command`, au format attendu par Illuminate\Support\Facades\
    | Process::run()). Ajouter, retirer ou réordonner une étape — ou une
    | cible entière — ne touche jamais le code du package : la liste des
    | environnements exposée par `GET /environnements` (et donc les filtres/
    | boutons de lancement côté frontend) s'adapte automatiquement au nombre
    | de cibles déclarées ici.
    |
    | Astuce : sur un VPS avec plusieurs versions de PHP/Node en parallèle
    | (ex. Plesk), ne jamais laisser `php`/`composer`/`yarn` nu dans une
    | commande — Process (via un worker de queue, sans shell interactif)
    | n'hérite d'aucun alias shell et peut résoudre un binaire différent de
    | celui attendu. Préférez env('DEPLOY_PHP_BIN', 'php') etc. avec le
    | chemin absolu en production.
    |
    | Exemple (backend Laravel + frontend Vite, adaptez à votre projet) :
    |
    | 'targets' => [
    |     'backend' => [
    |         'label' => 'Backend',
    |         'path' => env('DEPLOY_BACKEND_FOLDER'),
    |         'steps' => [
    |             ['label' => 'git pull', 'command' => ['git', 'pull', 'origin', env('DEPLOY_SUPERVISOR_GIT_BRANCH', 'main')]],
    |             ['label' => 'composer install', 'command' => ['composer', 'install', '--no-dev', '--optimize-autoloader']],
    |             ['label' => 'migrate', 'command' => [env('DEPLOY_PHP_BIN', 'php'), 'artisan', 'migrate', '--force']],
    |             ['label' => 'config cache', 'command' => [env('DEPLOY_PHP_BIN', 'php'), 'artisan', 'config:cache']],
    |         ],
    |     ],
    |     'frontend' => [
    |         'label' => 'Frontend',
    |         'path' => env('DEPLOY_FRONTEND_FOLDER'),
    |         'steps' => [
    |             ['label' => 'git pull', 'command' => ['git', 'pull', 'origin', env('DEPLOY_SUPERVISOR_GIT_BRANCH', 'main')]],
    |             ['label' => 'yarn install', 'command' => [env('DEPLOY_YARN_BIN', 'yarn'), 'install', '--frozen-lockfile']],
    |             ['label' => 'build', 'command' => [env('DEPLOY_YARN_BIN', 'yarn'), 'build']],
    |         ],
    |     ],
    | ],
    */
    'targets' => [],

];
