# Laravel Deploy Supervisor

Pipeline de déploiement supervisé pour applications Laravel : `git pull` +
build + reload par étapes **configurables**, historique en base, suivi
temps réel via broadcasting, déclenchement automatique par webhook
(GitHub, GitLab, Bitbucket) et notifications email.

Extrait du projet TCRM, généricisé pour être réutilisable tel quel dans
n'importe quel autre projet Laravel.

**Compatibilité** : PHP 8.2+, Laravel 10, 11, 12 ou 13.

## Fonctionnalités

- **Pipeline configurable** par étapes ordonnées (`git pull`, `composer
  install`, `migrate`, `yarn build`...), une ou plusieurs cibles, sans
  toucher au code du package — tout passe par `config/deploy-supervisor.php`.
- **Historique complet en base**, avec la sortie console de chaque étape.
- **Suivi temps réel** via broadcasting (Reverb, Pusher, ou tout driver
  compatible), sans jamais exposer la sortie console sur le canal public.
- **Déclenchement automatique** sur push, via webhook GitHub, GitLab ou
  Bitbucket.
- **Notifications email** au démarrage et à la fin de chaque déploiement.
- **CLI Artisan** pour déployer, générer un secret de webhook, ou en
  récupérer l'URL complète.

## Installation

Disponible sur [Packagist](https://packagist.org/packages/bamboguirassy/laravel-deploy-supervisor)
(mise à jour automatique à chaque push/tag via le hook GitHub) :

```bash
composer require bamboguirassy/laravel-deploy-supervisor:^1.2
```

Publier la config et la migration :

```bash
php artisan vendor:publish --tag=deploy-supervisor-config
php artisan vendor:publish --tag=deploy-supervisor-migrations
php artisan migrate
```

## Mise à jour

⚠️ **`composer require ...:^1.2` (ou toute contrainte `^1.x`) n'entraîne PAS
automatiquement la récupération de la dernière version 1.x à chaque
déploiement.** Si un `composer.lock` existe déjà avec une ancienne version
verrouillée, `composer install` réutilise cette version — même si votre
contrainte l'autoriserait à prendre plus récent. Pour forcer la mise à jour
vers la dernière version disponible sur Packagist (voir le
[CHANGELOG](CHANGELOG.md) pour le contenu de chaque version) :

```bash
composer update bamboguirassy/laravel-deploy-supervisor
php artisan vendor:publish --tag=deploy-supervisor-config --force
php artisan config:clear && php artisan config:cache
```

- `vendor:publish --force` écrase votre `config/deploy-supervisor.php`
  publié avec la version fournie par le package — pensez à sauvegarder vos
  personnalisations (adresses email, secrets, cibles...) avant, ou à
  comparer le diff après.
- Si vous cachiez la config en production, `config:clear`/`config:cache`
  est indispensable, sinon Laravel continue de servir l'ancien cache.
- Redémarrez le worker de queue (`php artisan horizon:terminate` ou
  relancer `queue:work`) : sinon il continue d'exécuter l'ancien code
  chargé en mémoire.

Rappel semver : `^1.2` autorise toute version `1.x` la plus récente
(ex. `1.2.2`, `1.3.0`...) mais jamais un futur `2.0.0` (breaking change).

## Prérequis : file d'attente (Horizon ou `queue:work`)

⚠️ **Étape facile à oublier, qui casse tout en silence.** Chaque
déploiement s'exécute dans un job (`RunDeploiementJob`) dispatché sur la
queue `config('deploy-supervisor.queue')` (par défaut `deploy`). Si
**aucun worker n'écoute cette queue**, `POST /deploiement` répond quand
même `202 Accepted` — mais rien ne s'exécute jamais. Le job reste en
attente silencieuse dans Redis, sans erreur visible ni côté API ni côté
logs applicatifs. Les notifications email (voir plus bas) partagent cette
même queue : sans worker, elles ne partent pas non plus.

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
   `command`). Une "cible" ici est le même concept que ce que l'API expose
   comme "environnement" via `GET /environnements` (voir "Utilisation").
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

### Variables d'environnement

Variables de base (voir les commentaires du fichier de config pour le
détail de chacune). Les variables spécifiques au webhook et aux
notifications email sont documentées dans leurs sections dédiées
ci-dessous, pas ici.

```env
DEPLOY_SUPERVISOR_TABLE=deploy_supervisor_deploiements
DEPLOY_SUPERVISOR_USER_MODEL=App\Models\User
DEPLOY_SUPERVISOR_GATE=manage-deploy-supervisor

# false pour désactiver complètement les routes du package (voir Sécurité)
DEPLOY_SUPERVISOR_ROUTES=true
DEPLOY_SUPERVISOR_ROUTE_PREFIX=api/deploiement

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
- **Désactiver les routes** (`DEPLOY_SUPERVISOR_ROUTES=false`) et les
  déclarer vous-même dans votre application, dans le groupe de middlewares
  de votre choix, en pointant vers
  `Bamboguirassy\DeploySupervisor\Http\Controllers\DeploiementController`
  (c'est l'approche utilisée par TCRM, qui a des middlewares applicatifs —
  `check.user.enabled`, `resolve.entreprise`, `ensure.admin` — que le
  package ne peut pas connaître).

Le canal de diffusion temps réel (`config('deploy-supervisor.channel')`),
lui, reste protégé par la Gate `DEPLOY_SUPERVISOR_GATE` — un canal de
broadcasting a besoin d'un callback booléen quoi qu'il arrive, donc ce
point-là n'est pas concerné par le choix ci-dessus. Non définie côté
application hôte = canal refusé par défaut (échec fermé).

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

## Webhook (déclenchement automatique sur push)

Désactivé par défaut :

```env
DEPLOY_SUPERVISOR_WEBHOOK_ENABLED=true
DEPLOY_SUPERVISOR_WEBHOOK_SECRET=

# Optionnel — change le préfixe des routes webhook (défaut affiché ici)
DEPLOY_SUPERVISOR_WEBHOOK_ROUTE_PREFIX=api/deploiement/webhook
```

Une fois activé, le package expose :

```
POST /api/deploiement/webhook/github
POST /api/deploiement/webhook/gitlab
POST /api/deploiement/webhook/bitbucket
```

Un push sur la branche `DEPLOY_SUPERVISOR_GIT_BRANCH` (par défaut `main`)
déclenche un déploiement asynchrone (queue) de **toutes** les cibles
configurées — même comportement que `POST /api/deploiement` sans `cibles`.
Toute autre branche est ignorée (réponse `200`, aucun déploiement créé).

⚠️ Ces routes ne portent PAS le middleware `auth:sanctum` (un webhook n'a
pas de session utilisateur) — l'authenticité est vérifiée par
signature/token propre à chaque fournisseur. **Sans `secret` configuré,
aucune requête n'est acceptée** (échec fermé).

Deux requêtes identiques (même commit) envoyées à quelques secondes
d'intervalle — fréquent en cas de retry réseau côté fournisseur — ne
déclenchent qu'un seul déploiement (déduplication par verrou de 30s sur le
SHA du commit).

### Générer un secret fort

```bash
php artisan deploy-supervisor:webhook-secret
```

Génère un secret aléatoire de 32 octets et l'écrit directement dans
`DEPLOY_SUPERVISOR_WEBHOOK_SECRET` dans `.env` (même principe que
`php artisan key:generate`) — demande confirmation si une valeur existe déjà
(`--force` pour l'écraser sans confirmation, `--show` pour juste afficher
une valeur générée sans toucher à `.env`). Ne la réutilisez pas ailleurs,
et ne la commitez jamais. La commande affiche aussi, juste après, l'URL
complète de chaque fournisseur (voir ci-dessous) prête à copier.

### Récupérer l'URL complète à configurer

```bash
php artisan deploy-supervisor:webhook-url
```

Affiche l'URL complète de chaque fournisseur, construite à partir de
`APP_URL` (donc jamais à reconstruire à la main) :

```
github    : https://votre-app.example/api/deploiement/webhook/github
gitlab    : https://votre-app.example/api/deploiement/webhook/gitlab
bitbucket : https://votre-app.example/api/deploiement/webhook/bitbucket?secret=...
```

Pour Bitbucket, le secret est directement inclus dans l'URL affichée (voir
la section Bitbucket ci-dessous, qui explique pourquoi). Assurez-vous que
`APP_URL` (dans `.env`) correspond bien à l'URL publique réelle de votre
application avant de copier ces URLs chez le fournisseur.

### GitHub

Dans les paramètres du dépôt → *Webhooks* → *Add webhook* :

- **Payload URL** : `https://votre-app.example/api/deploiement/webhook/github`
- **Content type** : `application/json`
- **Secret** : la même valeur que `DEPLOY_SUPERVISOR_WEBHOOK_SECRET`
- **Events** : `Just the push event`

GitHub signe le corps de la requête (HMAC-SHA256) dans le header
`X-Hub-Signature-256`, vérifié côté package.

### GitLab

Dans le dépôt → *Settings* → *Webhooks* :

- **URL** : `https://votre-app.example/api/deploiement/webhook/gitlab`
- **Secret token** : la même valeur que `DEPLOY_SUPERVISOR_WEBHOOK_SECRET`
- **Trigger** : `Push events` (branche configurée uniquement, ou toutes —
  le filtrage par branche est fait côté package de toute façon)

GitLab renvoie ce secret tel quel dans le header `X-Gitlab-Token`, comparé
côté package.

### Bitbucket

Bitbucket Cloud ne propose pas de champ "secret" natif pour ses webhooks :
le secret doit être ajouté directement dans l'URL, en query string.

Dans le dépôt → *Repository settings* → *Webhooks* :

- **URL** : `https://votre-app.example/api/deploiement/webhook/bitbucket?secret=VOTRE_SECRET`
- **Triggers** : `Repository` → `Push`

⚠️ Le secret apparaît ici en clair dans l'URL configurée côté Bitbucket
(visible par quiconque a accès aux paramètres du dépôt) — s'assurer que
l'URL est servie en HTTPS pour qu'il ne transite jamais en clair sur le
réseau.

## Notifications par email

Désactivées par défaut. Une fois activées, un email est envoyé à une liste
d'adresses configurée — indépendante des comptes utilisateurs Laravel,
typiquement une liste ops/astreinte — au **démarrage** et à la **fin** de
chaque déploiement.

```env
DEPLOY_SUPERVISOR_MAIL_ENABLED=true
DEPLOY_SUPERVISOR_MAIL_TO=ops@example.com,cto@example.com

# Optionnel — si renseignée, chaque email inclut un bouton vers cette page
# (URL + "/" + uid du déploiement) côté frontend de votre application.
DEPLOY_SUPERVISOR_FRONTEND_DEPLOIEMENT_PAGE_URL=https://votre-app.example/deploiements
```

- L'expéditeur utilise `config('mail.from')` de votre application (aucune
  config séparée) — assurez-vous que votre `MAIL_MAILER` est configuré.
- Les deux emails (démarrage et fin) sont des Mailables `ShouldQueue`,
  explicitement mis sur la **même queue** que le pipeline
  (`config('deploy-supervisor.queue')`, `deploy` par défaut) : aucun worker
  supplémentaire à faire tourner au-delà de celui déjà requis pour le
  pipeline (voir "Prérequis : file d'attente" ci-dessus).
- Le mail de fin inclut, pour chaque étape en échec, un extrait de sa
  sortie console (`output_tail`, 40 dernières lignes) — rien en cas de
  succès, pour rester lisible.
- L'échec de l'envoi (SMTP injoignable, config manquante...) est rattrapé
  et journalisé (`Log::warning`) — ne fait jamais échouer le déploiement
  lui-même.
- Contenu personnalisable : les vues sont publiables avec
  `php artisan vendor:publish --tag=deploy-supervisor-views` (copiées dans
  `resources/views/vendor/deploy-supervisor/`).

## Robustesse

- Toute exception pendant une étape (ex. `ProcessTimedOutException` si une
  commande reste bloquée jusqu'au timeout) est rattrapée : le déploiement
  se termine toujours (`succes` ou `echec`), jamais figé indéfiniment en
  `en_cours`.
- La diffusion temps réel est un confort d'UX, pas une garantie : si le
  serveur de broadcasting est injoignable, le pipeline (et son statut
  final en base) n'en dépend pas.
- Les notifications email suivent la même règle : une erreur d'envoi est
  journalisée, jamais fatale pour le pipeline.
