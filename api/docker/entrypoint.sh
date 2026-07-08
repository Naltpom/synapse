#!/bin/sh
set -e

echo "[synapse] attente de la base de données…"
tries=0
until php bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1; do
  tries=$((tries + 1))
  if [ "$tries" -ge 30 ]; then
    echo "[synapse] base injoignable après 30 tentatives, abandon." >&2
    exit 1
  fi
  sleep 2
done

echo "[synapse] synchronisation du schéma (démo : schema:update ; en production réelle, migrations versionnées)…"
php bin/console doctrine:schema:update --force --complete --no-interaction

echo "[synapse] chargement des données de démonstration (réinitialisées à chaque démarrage)…"
php bin/console doctrine:fixtures:load --no-interaction

echo "[synapse] démarrage du serveur."
exec "$@"
