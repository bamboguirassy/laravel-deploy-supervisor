# Laravel Deploy Supervisor

Pipeline de déploiement supervisé pour applications Laravel : `git pull` +
build + reload par étapes **configurables**, historique en base, suivi
temps réel via broadcasting (Reverb, Pusher, ou tout driver compatible).

Extrait du projet TCRM, généricisé pour être réutilisable tel quel dans
n'importe quel autre projet Laravel.

## Installation

Disponible sur [Packagist](https://packagist.org/packages/bamboguirassy/laravel-deploy-supervisor)
(mise à jour automatique à chaque push/tag via le hook GitHub) :

```bash
composer require bamboguirassy/laravel-deploy-supervisor:^1.0
```

Publier la config et la migration :

```bash
php artisan vendor:publish --tag=deploy-supervisor-config
php artisan vendor:publish --tag=deploy-supervisor-migrations
php artisan migrate
```

## Prérequis : file d'attente (Horizon ou `queue:work`)

⚠️ **Étape facile à oublier, qui casse tout en silence.** Chaque
déploiement s'exécute dans un job (`RunDeploiementJob`) dispatché sur la
queue `config('deploy-supervisor.queue')` (par défaut `deploy`). Si
**aucun worker n'écoute cette queue**, `POST /deploiement` répond quand
même `202 Accepted` — mais rien ne s'exécute jamais. Le job reste en
attente silencieuse dans Redis, sans erreur visible ni côté API ni côté
logs applicatifs.

**Avec Horizon**, ajoutez la queue à un supervisor de `config/horizon.php` :

```php
'environments' => [
    'production' => [
        'supervisor-deploy' => [
            'connection' => 'redis',
            'queue' => ['deploy'],
            'balance' => 'simple',
            'maxProcesses' => 1,
            'tries' => 1,
            // Un déploiement peut prendre plusieurs minutes — laisser de
            // la marge par rapport à `config('deploy-supervisor.timeout')`.
            'timeout' => 960,
        ],
        // ... vos autres supervisors (default, notifications, etc.)
    ],
],
```

Puis redémarrer Horizon (`php artisan horizon:terminate`, Supervisor le
relance) pour qu'il prenne en compte le nouveau supervisor.

**Sans Horizon**, un worker classique dédié suffit (à superviser vous-même,
ex. avec Supervisor) :

```bash
php artisan queue:work redis --queue=deploy --timeout=960 --tries=1
```

Vérifiez que ça fonctionne en lançant un déploiement de test
(`php artisan deploy-supervisor:run --sync` contourne la queue et exécute
en synchrone, utile pour confirmer que le pipeline lui-même marche — mais
ne remplace pas ce test en mode asynchrone normal, via l'API ou sans
`--sync`, pour valider que le worker écoute bien).

## Configuration minimale

Dans `config/deploy-supervisor.php` (publié), renseigner au moins :

1. **`targets`** — un exemple complet est en commentaire dans le fichier
   publié ; définit pour chaque cible (`backend`, `frontend`, ou tout autre
   nom) un dossier (`path`) et une liste ORDONNÉE d'étapes (`label` +
   `command`).
2. **`routes.middleware`** — voir la section **Sécurité** ci-dessous, à lire
   avant toute mise en production.
3. **`gate`** — protège uniquement le canal de diffusion temps réel (voir
   Sécurité) :

   ```php
   Gate::define('manage-deploy-supervisor', fn ($user) => $user->is_admin);
   ```

4. **`user_model`** — le modèle User de votre application (par défaut
   `App\Models\User`).
5. **`declenche_par_formatter`** — nom d'une classe invokable
   (`__invoke($user): array`) qui formate l'utilisateur "déclenché par"
   pour l'API. **Doit être un nom de classe (string), jamais une closure**
   — `php artisan config:cache` sérialise la config avec `var_export()`,
   qui ne sait pas représenter une `Closure`. Créez la vôtre si vos
   colonnes User diffèrent (`name`/`nom_complet`/`uid`...) :

   ```php
   // App\Support\DeclencheParFormatter.php
   class DeclencheParFormatter
   {
       public function __invoke($user): array
       {
           return ['uid' => $user->uid, 'nom_complet' => $user->nom_complet];
       }
   }

   // config/deploy-supervisor.php
   'declenche_par_formatter' => \App\Support\DeclencheParFormatter::class,
   ```

## Sécurité

⚠️ **Les routes du package ne portent par défaut AUCUNE vérification de
permission/rôle** — uniquement `config('deploy-supervisor.routes.middleware')`
(par défaut `['api', 'auth:sanctum']`, donc juste "être authentifié"). C'est
volontaire : ce package ne peut pas deviner votre logique d'autorisation
(rôles, permissions, `is_admin`...). **Sans action de votre part, n'importe
quel utilisateur authentifié peut déclencher, consulter et supprimer des
déploiements.**

À vous d'ajouter votre propre garde, typiquement l'une de ces deux options :

- **Ajouter votre middleware** à `config('deploy-supervisor.routes.middleware')`
  (ex. `['api', 'auth:sanctum', 'can:manage-deploy-supervisor']`, ou un
  middleware maison) ;
- **Désactiver `routes.enabled`** et déclarer vous-même ces routes dans
  votre application, dans le groupe de middlewares de votre choix, en
  pointant vers `Bamboguirassy\DeploySupervisor\Http\Controllers\DeploiementController`
  (c'est l'approche utilisée par TCRM, qui a des middlewares applicatifs —
  `check.user.enabled`, `resolve.entreprise`, `ensure.admin` — que le
  package ne peut pas connaître).

Le canal de diffusion temps réel (`config('deploy-supervisor.channel')`),
lui, reste protégé par la Gate `config('deploy-supervisor.gate')` — un canal
de broadcasting a besoin d'un callback booléen quoi qu'il arrive, donc ce
point-là n'est pas concerné par le choix ci-dessus.

Variables d'environnement utiles (voir les commentaires du fichier de
config pour le détail de chacune) :

```env
DEPLOY_SUPERVISOR_TABLE=deploy_supervisor_deploiements
DEPLOY_SUPERVISOR_GATE=manage-deploy-supervisor
DEPLOY_SUPERVISOR_CHANNEL=deploy-supervisor
DEPLOY_SUPERVISOR_QUEUE=deploy
DEPLOY_SUPERVISOR_GIT_BRANCH=main
DEPLOY_SUPERVISOR_TIMEOUT=900

# Optionnel — authentification git du pipeline (remote HTTPS uniquement) :
# utile si le worker de queue n'a pas accès à l'agent SSH / au credential
# helper de l'utilisateur ayant cloné le dépôt manuellement.
DEPLOY_SUPERVISOR_GIT_USERNAME=
DEPLOY_SUPERVISOR_GIT_TOKEN=
```

## Utilisation

### Via l'API (routes enregistrées automatiquement)

```
POST   /api/deploiement                Déclenche un déploiement
POST   /api/deploiement/search         Historique paginé
GET    /api/deploiement/environnements Liste des environnements (déduite de config('deploy-supervisor.targets'))
GET    /api/deploiement/{uid}          Détail complet (avec sortie console)
DELETE /api/deploiement/{uid}          Supprime un déploiement (sauf en_cours)
```

`GET /api/deploiement/environnements` retourne `{ code, label }` pour
chaque cible configurée — s'adapte automatiquement si vous ajoutez ou
retirez une cible dans `config('deploy-supervisor.targets')`, sans aucun
changement de code frontend nécessaire :

```json
{ "success": true, "data": [{ "code": "backend", "label": "Backend" }, { "code": "frontend", "label": "Frontend" }] }
```

### Via la CLI

```bash
# Déploie toutes les cibles configurées, en file d'attente
php artisan deploy-supervisor:run

# Une seule cible
php artisan deploy-supervisor:run --cible=backend

# Synchrone — attend la fin dans ce process (secours si le worker de queue est down)
php artisan deploy-supervisor:run --sync
```

## Suivi temps réel

Le pipeline diffuse 3 types d'événements légers sur le canal privé
`deploy-supervisor` (nom configurable) — **jamais de sortie console
dedans**, pour rester bien en dessous des limites de payload des serveurs
WebSocket (10 Ko par défaut sur Reverb) :

- `deploiement.etape` — `{ uid, cible, label, statut, exit_code, duration_ms }`
- `deploiement.cible` — `{ uid, cible, statut }`
- `deploiement.termine` — `{ uid, statut, termine_le }`

Le détail complet (avec la sortie console de chaque étape, `output_tail`)
reste toujours en base — à récupérer via `GET /api/deploiement/{uid}` côté
frontend, typiquement après réception de l'événement `deploiement.termine`.

Exemple de client (pusher-js, adaptable à laravel-echo) :

```ts
const channel = pusher.subscribe('private-deploy-supervisor')
channel.bind('deploiement.etape', (payload) => { /* mettre à jour l'étape */ })
channel.bind('deploiement.cible', (payload) => { /* mettre à jour la cible */ })
channel.bind('deploiement.termine', (payload) => {
  // aller chercher le détail complet, y compris en cas d'échec
  fetch(`/api/deploiement/${payload.uid}`)
})
```

## Robustesse

- Toute exception pendant une étape (ex. `ProcessTimedOutException` si une
  commande reste bloquée jusqu'au timeout) est rattrapée : le déploiement
  se termine toujours (`succes` ou `echec`), jamais figé indéfiniment en
  `en_cours`.
- La diffusion temps réel est un confort d'UX, pas une garantie : si le
  serveur de broadcasting est injoignable, le pipeline (et son statut
  final en base) n'en dépend pas.
