# ADR 0001 — Choix de la stack : Symfony 8 + Vue 3 + PostgreSQL

Date : 2026-07-08 · Statut : acceptée

## Contexte

Synapse est un ERP interne pour un cabinet de conseil en cybersécurité (~400 collaborateurs) :
CRM, staffing, suivi de projet, facturation. Contraintes structurantes :

- **Un seul développeur au démarrage** (équipe susceptible de grandir à 2-3 devs) ;
- Système de **gestion transactionnel** : cohérence des données, modèle métier riche,
  règles de gestion (staffing, facturation) plus que rendu complexe côté client ;
- Utilisateurs internes uniquement, volumétrie modérée, pas de SEO ni de SSR nécessaires ;
- L'annonce mentionne une stack indicative Next.js/TypeScript/Node/PostgreSQL,
  avec **carte blanche** et demande d'être force de proposition.

## Décision

- **API : Symfony 8 (PHP 8.4)** — monolithe modulaire exposant une API JSON de session.
- **Front : Vue 3 + TypeScript + Vite + Tailwind CSS 4** — SPA légère servie par nginx.
- **Base : PostgreSQL 16** en conteneur (SQLite en développement local et en test).

## Justification

1. **Vélocité immédiate et durable.** C'est la stack que je maîtrise en profondeur :
   chaque heure investie produit du logiciel, pas de la montée en compétence. Pour une
   création de poste où il faut prouver de la valeur vite, c'est le critère dominant.
2. **Le bon outil pour un ERP.** Doctrine (transactions, unité de travail, migrations),
   le composant Security (sessions, hashers, voters), le Serializer et la validation
   forment un socle éprouvé pour un système de gestion — là où l'écosystème Node impose
   d'assembler ces briques soi-même.
3. **La modernité demandée est conservée là où elle a du sens** : TypeScript strict,
   Vite, Tailwind 4, PostgreSQL — la moitié front de la stack indicative est adoptée telle quelle.
4. **Réversibilité.** L'API étant du JSON sur HTTP sans couplage au front, remplacer la SPA
   Vue par du Next.js plus tard resterait un chantier front isolé.

## Alternatives considérées

- **Next.js full-stack (stack indicative)** : excellent choix générique ; écarté ici car
  le cœur du besoin est transactionnel/métier (points 1 et 2), et le coût de ma montée en
  gamme sur un framework full-stack Node aurait été payé par le projet.
- **Laravel** : équivalent fonctionnel de Symfony ; Symfony est préféré pour la rigueur
  de sa structure (DI, composants découplés) sur un projet destiné à durer et à accueillir
  une équipe.

## Conséquences

- Session + cookie same-origin (pas de CORS, pas de JWT à stocker côté client).
- Le recrutement futur (2-3 devs) devra cibler PHP/Symfony ou des profils full-stack à l'aise en JS/TS.
