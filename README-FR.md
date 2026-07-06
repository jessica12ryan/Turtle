# Turtle — Portail de Gestion Locative

Une application web pour gérer les propriétés locatives, les locataires, les baux, les tickets de maintenance, les paiements de loyer et les demandes de location. Conçue pour les environnements Docker et les modules complémentaires Home Assistant avec prise en charge complète de l'ingress.

## Démarrage Rapide

### Docker

```bash
git clone --branch stable https://github.com/jessica12ryan/Turtle.git
cd Turtle
docker compose up -d --build
open http://localhost
```

Au premier démarrage, un assistant de configuration s'affiche. Choisissez **Nouvelle installation** pour configurer les informations du site ou **Restaurer une sauvegarde** pour téléverser un fichier de sauvegarde `.turtle`.

**Test des courriels :** http://localhost:8025 (Mailpit)

### Module complémentaire Home Assistant

Turtle est disponible comme module complémentaire Home Assistant en deux variantes :

| Module | Canal | Source |
|--------|-------|--------|
| **Turtle** | Stable | `turtle-ha/` |
| **Turtle (Dev)** | Développement | `turtle-ha-dev/` (construit à partir de `master`) |

Les deux prennent en charge l'**ingress** (intégré dans l'interface HA) et l'**accès direct** via le port.

## Fonctionnalités

- **Propriétés** — gérer les détails, photos, type de chauffage, dépôts de garantie, statut d'affichage
- **Locataires** — locataires principal/secondaire, dates de bail, départ planifié, archivage automatique
- **Baux et documents** — téléversement avec auto-titrage, types de documents, courriel avec pièces jointes
- **Tickets de maintenance** — créer, assigner, commenter, suivi des statuts, pièces jointes
- **Tableau de bord des loyers** — suivi des paiements par propriété, indicateurs de statut (payé/partiel/impayé)
- **Demandes de location** — formulaire de soumission public, flux de révision, conversion en locataire
- **Assistant IA** — requêtes en langage naturel sur les propriétés, locataires, tickets
- **Calendrier** — dates d'emménagement, de fin de bail et de départ planifié
- **Ressources** — page de liens partagés, catégories générales et réservées au personnel
- **Sauvegarde et restauration** — sauvegarde complète du système (format `.turtle`) via les paramètres admin
- **Courriel** — client SMTP léger, préférences de notification par rôle
- **Contrôle d'accès** — Administrateur, Propriétaire, Gestionnaire immobilier, Maintenance, Locataire

## Autorisations

Le contrôle d'accès utilise un middleware de routage et des autorisations granulaires configurables dans **Paramètres → Autorisations**.

| Rôle | Portée |
|------|--------|
| **Administrateur** | Accès illimité |
| **Propriétaire** | Gestion complète des propriétés, locataires, personnel, baux, tickets, loyers |
| **Gestionnaire immobilier** | Propriétés assignées, locataires, tickets, loyers |
| **Maintenance** | Tickets (voir, mettre à jour le statut, commenter) |
| **Locataire** | Ses propres tickets, baux assignés, statut du loyer, ressources |

## Courriel

Configuré via **Paramètres → Général** (SMTP) et **Paramètres → Notifications** (préférences par rôle).

- **Docker dev** : Mailpit intégré à `mailpit:1025`, interface à `localhost:8025`
- **Module HA** : Mailpit intégré dans le conteneur, port configurable
- **SMTP personnalisé** : Défini dans l'interface des paramètres ou le fichier `.env`

## Localisation

- Langues : anglais, français, espagnol — configurables dans Paramètres ou par utilisateur
- Fuseau horaire : valeur globale par défaut + remplacement par utilisateur
- Pays : Canada ou États-Unis (provinces/états, formats postaux/zip)
- Synchronisation NTP : serveur configurable, mise en cache horaire, alerte de dérive sur le tableau de bord

## Structure du Projet

```
www/                  Racine Apache — contrôleurs, vues, framework principal
database/             Schéma (schema.sql), données initiales (seed.sql), migrations (migrate.sh)
docker/php/           Dockerfile + point d'entrée + configuration PHP
turtle-ha/            Module complémentaire Home Assistant production
turtle-ha-dev/        Module complémentaire Home Assistant développement
docker-compose.yml    Environnement de développement local
update.sh             Script de mise à jour (git pull + docker compose up)
```

## Mise à Jour

**Dans l'application** (admin) : Paramètres → Mises à jour → Appliquer la mise à jour (exécute `git pull` + migrations).

**Manuelle** :
```bash
git checkout stable && git pull && docker compose up -d --build
```

**Module HA** : Reconstruire le module ou utiliser le programme de mise à jour dans le conteneur.

## Données Persistantes

- Base de données MySQL → volume Docker `mysql-data`
- Fichiers téléversés (baux, photos, tickets) → volume Docker `turtle-storage`

## Contribution

Consultez [CONTRIBUTING.md](CONTRIBUTING.md) pour la configuration de développement, les normes de codage et les directives pour les demandes de tirage.

## Versions

- **`stable`** — Versions publiées pour la production
- **`master`** — Branche de développement active
