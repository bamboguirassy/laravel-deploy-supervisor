# Changelog

Toutes les modifications notables de ce package sont documentées ici.
Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
versionnement [SemVer](https://semver.org/lang/fr/).

## [1.4.0] - 2026-08-04

### Modifié
- **BREAKING CHANGE** : l'URL du webhook porte désormais la cible à
  déployer (`POST {prefix}/webhook/{provider}/{target}` au lieu de
  `POST {prefix}/webhook/{provider}`). Permet d'avoir un dépôt git distinct
  par cible (ex. `backend` et `frontend` séparés) : chaque dépôt reçoit sa
  propre URL, pointant uniquement vers sa cible — un push sur ce dépôt ne
  déclenche plus le déploiement de *toutes* les cibles configurées.
  `deploy-supervisor:webhook-url` accepte maintenant un argument optionnel
  `{target?}` et affiche une URL par cible × fournisseur.

## [1.3.0] - 2026-08-04

### Ajouté
- Webhook git (`POST {prefix}/webhook/{github|gitlab|bitbucket}`), désactivé
  par défaut, déclenche le pipeline sur push de la branche configurée
  (authenticité vérifiée par signature/token propre à chaque fournisseur,
  déduplication par verrou sur le SHA du commit).
- Notifications email (démarrage/fin de déploiement) vers une liste
  d'adresses configurée, désactivées par défaut.
- Commandes `deploy-supervisor:webhook-secret` (génère/écrit le secret dans
  `.env`) et `deploy-supervisor:webhook-url` (affiche l'URL complète du
  webhook basée sur `APP_URL`).

## [1.2.2] - 2026-07-31

### Documentation
- Documentation du prérequis Horizon/`queue:work` pour la queue `deploy`
  (sans worker, `POST /deploiement` répond `202` mais rien ne s'exécute).

## [1.2.1] - 2026-07-31

### Corrigé
- `declenche_par_formatter` rendu sérialisable pour `config:cache` (remplacé
  par un nom de classe invokable au lieu d'une `Closure` inline).

**BREAKING CHANGE** : `declenche_par_formatter` doit être un nom de classe
(string) implémentant `__invoke($user): array`, plus une closure.

## [1.2.0] - 2026-07-31

### Modifié
- **BREAKING CHANGE** : retrait de l'autorisation intégrée par défaut sur
  les routes HTTP du package — seul `config('deploy-supervisor.routes.middleware')`
  s'applique désormais (par défaut `auth:sanctum`, sans vérification de
  rôle/permission). L'app hôte doit gérer elle-même son autorisation.

## [1.1.0] - 2026-07-31

### Ajouté
- Route `GET /environnements` pour lister dynamiquement les cibles
  configurées.

## [1.0.3] - 2026-07-31

### Documentation
- Retrait de la mention repositories/VCS, obsolète depuis la publication
  sur Packagist.

## [1.0.2] - 2026-07-31

### Documentation
- Installation via Packagist (plus de repli VCS nécessaire).

## [1.0.0] / [1.0.1] - 2026-07-31

### Corrigé
- Nom de l'événement de fin de déploiement et pagination du contrôleur.

### Ajouté
- Extraction initiale du pipeline de déploiement supervisé (issu de TCRM) :
  pipeline configurable par étapes, historique en base, suivi temps réel
  via broadcasting, support Laravel 10 à 13.
