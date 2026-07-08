# ADR 0003 — Persistance : PostgreSQL en Docker, SQLite en local, schéma et données de démo

Date : 2026-07-08 · Statut : acceptée

## Contexte

La démo doit tourner en une commande (`docker compose up`) tout en restant agréable à
développer sans conteneur sous Windows.

## Décision

| Environnement | Base | Schéma | Données |
|---|---|---|---|
| Docker (`APP_ENV=prod`) | PostgreSQL 16 | `doctrine:schema:update` au démarrage | fixtures rechargées à chaque démarrage |
| Dev local | SQLite (`var/data_dev.db`) | `doctrine:schema:create` | fixtures à la demande |
| Tests | SQLite (`var/data_test.db`) | créé par le socle de test | fixtures par processus |

- Le `DATABASE_URL` est la seule variable qui change : Doctrine abstrait le reste,
  et les agrégations sensibles au dialecte (groupement par mois) sont faites en PHP.
- Les **fixtures sont générées avec une graine fixe** (Faker seedé) : jeu de données
  reproductible, 100 % fictif, aux couleurs du métier (practices, missions PASSI/IAM/SOC…).

## Ce qui changerait en production réelle

Ces choix sont des raccourcis **assumés et documentés** pour une démo :

1. `doctrine:schema:update` serait remplacé par des **migrations versionnées**
   (doctrine-migrations est déjà installé) ;
2. les fixtures ne seraient évidemment plus rechargées au démarrage ;
3. les secrets (`APP_SECRET`, mot de passe base) sortiraient du `compose.yaml`
   pour un gestionnaire de secrets (Vault, secrets Docker/K8s) ;
4. sauvegardes, chiffrement au repos et politique de rétention du journal d'audit
   seraient définis avec le RSSI — chez Synetis, l'ERP interne se doit d'être exemplaire.
