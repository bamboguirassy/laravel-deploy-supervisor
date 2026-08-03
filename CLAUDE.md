# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Ce que c'est

Package Laravel installable (`bamboguirassy/laravel-deploy-supervisor`, PSR-4
`Bamboguirassy\DeploySupervisor\` → `src/`) qui exécute un pipeline de
déploiement (`git pull` + build + reload) par étapes configurables, avec
historique en base et suivi temps réel via broadcasting. Pas d'app Laravel
autonome : ce dépôt EST le package, consommé via `composer require` par une
app hôte (ex. TCRM, dont il a été extrait et généricisé).

Il n'y a pas de suite de tests, ni de linter/formatter configuré dans ce
dépôt (pas de `phpunit.xml`, pas de config Pint). Il n'y a donc pas de
commande build/lint/test à lancer ici — la vérification se fait par lecture
de code et, si besoin, en installant le package dans une app Laravel hôte
réelle pour un test d'intégration manuel.

## Architecture

Deux couches bien séparées, à ne pas mélanger :

- **`DeploiementService`** (`src/Services/DeploiementService.php`) — couche
  métier/BDD/HTTP : crée la ligne `Deploiement`, dispatch le job, gère la
  recherche paginée et la suppression. Ne touche jamais à l'exécution des
  commandes.
- **`DeploymentRunner`** (`src/Services/DeploymentRunner.php`) — moteur
  d'exécution réel : parcourt `config('deploy-supervisor.targets')` cible
  par cible puis étape par étape, exécute chaque commande via
  `Illuminate\Support\Facades\Process`, sauvegarde le `Deploiement` et
  diffuse un événement après CHAQUE étape (pas seulement en fin de run).

Flux d'un déploiement : `DeploiementController` (HTTP) ou
`DeployRunCommand` (CLI, même code que l'API) → `DeploiementService::trigger()`
crée le `Deploiement` (statut `en_cours`) → dispatch `RunDeploiementJob` sur
la queue `config('deploy-supervisor.queue')` (par défaut `deploy`) →
`RunDeploiementJob::handle()` appelle `DeploymentRunner::run()`.

Deux invariants à préserver dans `DeploymentRunner` si on y touche :

- **Jamais de `Deploiement` figé en `en_cours`.** Deux niveaux de `catch
  (\Throwable)` (autour de la boucle par cible dans `run()`, et autour de la
  boucle par étape dans `executerCibles()`) garantissent qu'une exception
  (ex. `ProcessTimedOutException`) termine toujours le déploiement en
  `echec` plutôt que de laisser le job mourir en silence.
- **Séparation stricte BDD / diffusion temps réel.** `sauvegarder()` écrit
  toujours le détail complet, `output_tail` inclus (`resultat` JSON sur
  `Deploiement`). `diffuser()` (événement `DeploiementProgression`,
  `src/Events/DeploiementProgression.php`) ne contient JAMAIS de sortie
  console — volontairement minuscule pour rester sous les limites de
  payload WebSocket (10 Ko par défaut sur Reverb). Si on ajoute un champ à
  un événement diffusé, vérifier qu'il n'y introduit pas de sortie console
  ou de payload potentiellement volumineux. `diffuser()` catch aussi toute
  exception : un serveur de broadcasting injoignable ne doit jamais faire
  échouer le pipeline ni son statut en base.

Le modèle `Deploiement` (`src/Models/Deploiement.php`) route sur `uid`
(UUID généré à la création, pas l'id auto-incrément) et lit son nom de table
dynamiquement depuis la config dans son constructeur (pas de propriété
`$table` statique) — permet à l'app hôte de renommer la table sans toucher
au package.

Toute la configurabilité (cibles, étapes, queue, canal, gate, timeout,
formatter d'utilisateur) passe par `config/deploy-supervisor.php`, publiée
chez l'app hôte via `vendor:publish --tag=deploy-supervisor-config`. Le
`DeploySupervisorServiceProvider` merge cette config par défaut
(`mergeConfigFrom`), enregistre les routes conditionnellement
(`routes.enabled`), et définit le canal de broadcasting protégé par Gate
(échec fermé si la Gate n'est pas définie côté app hôte).

`declenche_par_formatter` (config) doit être un **nom de classe** (string),
jamais une closure — `php artisan config:cache` sérialise via
`var_export()`, qui ne sait pas représenter une `Closure`.

### Sécurité — point à ne jamais perdre de vue

Les routes du package (`routes/api.php`) ne portent **aucune vérification de
permission/rôle** par elles-mêmes — seul `config('deploy-supervisor.routes.middleware')`
s'applique (par défaut `['api', 'auth:sanctum']`, donc juste
"authentifié"). C'est un choix délibéré (le package ne peut pas deviner la
logique d'autorisation de l'app hôte), documenté en détail dans le README
et dans les commentaires de `config/deploy-supervisor.php`. Ne jamais
réintroduire une autorisation "intégrée" par défaut dans les routes sans
que ce soit explicitement demandé (un commit précédent l'a justement
retirée — voir `fb1c42e`). Le canal de broadcasting, lui, reste protégé par
la Gate `config('deploy-supervisor.gate')`.

### Webhook et notifications (déclenchement/observabilité, ajoutés après le cœur du pipeline)

- **Webhook** (`src/Http/Controllers/WebhookController.php`,
  `src/Support/Webhook/*`) — `POST {prefix}/webhook/{github|gitlab|bitbucket}`,
  désactivé par défaut (`webhook.enabled`), volontairement SANS
  `auth:sanctum` (authenticité vérifiée par signature/token propre à chaque
  fournisseur, résolu par nom de classe via `webhook.providers` — même
  pattern que `declenche_par_formatter`). Ne déclenche que si la branche
  pushée correspond à `config('deploy-supervisor.branch')` ; dédup par
  `Cache::lock()` sur le SHA du commit.
- **Notifications email** (`src/Services/DeploymentNotifier.php`,
  `src/Mail/*`, `resources/views/mail/*`) — désactivées par défaut
  (`notifications.mail.enabled`), envoyées au démarrage et à la fin de
  chaque déploiement à une liste d'adresses fixe (PAS liée aux comptes
  Laravel). Suit la même philosophie que `diffuser()` :
  `DeploymentNotifier::envoyer()` catch toute exception, une notif mail ne
  doit jamais faire échouer le pipeline. Les Mailables (`ShouldQueue`) sont
  explicitement mis sur `config('deploy-supervisor.queue')` — pas de
  nouvelle queue à faire écouter côté app hôte. Points d'appel dans
  `DeploymentRunner::run()` : `notifier->demarrage()` tout au début,
  `notifier->fin()` tout à la fin (une fois `statut`/`resultat` déjà
  sauvegardés en base).
- `DeclencheParPresenter` (`src/Support/DeclencheParPresenter.php`) —
  logique de résolution de `declenche_par_formatter` extraite de
  `DeploiementResource` pour être réutilisée à l'identique par les
  Mailables ; toute évolution de ce formatage doit passer par cette classe
  unique, pas être dupliquée.

### Piège opérationnel connu

`RunDeploiementJob` est `ShouldQueue` sur la queue `config('deploy-supervisor.queue')`.
Sans worker qui écoute cette queue (Horizon ou `queue:work`), `POST /deploiement`
répond `202` mais rien ne s'exécute jamais, sans erreur visible — voir la
section "Prérequis : file d'attente" du README avant de diagnostiquer un
déploiement qui ne démarre jamais.
