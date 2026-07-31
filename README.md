# Laravel Deploy Supervisor

Pipeline de déploiement supervisé pour applications Laravel : `git pull` +
build + reload par étapes **configurables**, historique en base, suivi
temps réel via broadcasting (Reverb, Pusher, ou tout driver compatible).

Extrait du projet TCRM, généricisé pour être réutilisable tel quel dans
n'importe quel autre projet Laravel.

## Installation

```bash
composer require bamboguirassy/laravel-deploy-supervisor:^1.0
```

<details>
<summary>Installation avant publication sur Packagist (dépôt VCS)</summary>

Si le package n'est pas (encore) sur [packagist.org](https://packagist.org),
ajoutez le dépôt VCS dans le `composer.json` du projet consommateur :

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/bamboguirassy/laravel-deploy-supervisor.git" }
]
```

puis lancez la même commande `composer require` ci-dessus. Une fois publié
sur Packagist, cette étape n'est plus nécessaire.
</details>

Publier la config et la migration :

```bash
php artisan vendor:publish --tag=deploy-supervisor-config
php artisan vendor:publish --tag=deploy-supervisor-migrations
php artisan migrate
```

## Configuration minimale

Dans `config/deploy-supervisor.php` (publié), renseigner au moins :

1. **`targets`** — un exemple complet est en commentaire dans le fichier
   publié ; définit pour chaque cible (`backend`, `frontend`, ou tout autre
   nom) un dossier (`path`) et une liste ORDONNÉE d'étapes (`label` +
   `command`).
2. **`gate`** — nom d'une Gate à définir dans **votre** application (ex.
   dans un `ServiceProvider`) :

   ```php
   Gate::define('manage-deploy-supervisor', fn ($user) => $user->is_admin);
   ```

   Sans cette Gate définie, l'accès est refusé par défaut (échec fermé) —
   ni les routes API, ni le canal de diffusion ne sont accessibles.

3. **`user_model`** — le modèle User de votre application (par défaut
   `App\Models\User`).

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
POST   /api/deploiement            Déclenche un déploiement
POST   /api/deploiement/search     Historique paginé
GET    /api/deploiement/{uid}      Détail complet (avec sortie console)
DELETE /api/deploiement/{uid}      Supprime un déploiement (sauf en_cours)
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
