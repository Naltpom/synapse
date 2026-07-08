# Synapse — ERP de démonstration pour un cabinet de conseil en cybersécurité

> **Le système nerveux opérationnel du cabinet.** CRM, staffing, projets et facturation
> dans un seul outil, pensé pour le quotidien d'un cabinet de conseil en cybersécurité.

**Démonstration technique** réalisée dans le cadre du processus de recrutement
*Product Engineer ERP* de Synetis. Projet personnel, **non affilié à Synetis** —
toutes les données (clients, consultants, missions, factures) sont **fictives** et
générées avec une graine fixe.

---

## Démarrage en une commande

```bash
docker compose up --build
```

Puis ouvrir **http://localhost:8082**.

| Compte | E-mail | Rôle |
|---|---|---|
| Direction | `direction@synapse.demo` | ROLE_ADMIN |
| Staffing | `staffing@synapse.demo` | ROLE_MANAGER |
| Commerce | `commerce@synapse.demo` | ROLE_USER |

Mot de passe commun : `Synapse!2026` — les données de démo sont réinitialisées à chaque démarrage.

<details>
<summary>Développement local sans Docker</summary>

```bash
# API (PHP 8.4 + Composer) — SQLite, zéro configuration
cd api
composer install
php bin/console doctrine:schema:create
php bin/console doctrine:fixtures:load --no-interaction
php -S 127.0.0.1:8000 -t public

# Front (Node 22)
cd app
npm install
npm run dev        # http://127.0.0.1:4300 (proxy /api → :8000)
```
</details>

## Ce que la démo montre

- **Vue d'ensemble** : héro à sparklines (staffing, CA vs objectif annuel, pipeline pondéré,
  retards de paiement), facturation empilée payée/en attente sur 12 mois, occupation par
  practice, colonne « À traiter » avec actions directes.
- **Assistant Synapse (⌘K)** : commandes en langage naturel (scénarios mockés dans cette
  itération) avec trace des outils MCP appelés, résumé et **confirmation obligatoire** avant
  action — l'UI est prête à recevoir le backend LLM + MCP (interface `AssistantEngine`).
- **Congés & validations** : calendrier d'équipe croisant missions et absences, workflow
  d'approbation réservé aux managers, provenance « créée via assistant » tracée.
- **CRA / feuille de temps** : grille semaine consultant × activité (0 / ½ / 1, plafond
  d'une journée), soumission puis validation manager, saisie verrouillée après soumission.
- **Marges & rentabilité** (managers/direction) : CA facturé vs coût estimé des
  affectations, marge par mission et par client, méthode de calcul affichée.
- **Fiche client 360°** : missions, factures, contacts et pipeline du client en une page.
- **Recherche globale** dans la palette ⌘K (clients, consultants, missions, factures) ;
- **Ma sécurité** : chacun voit son historique de connexions (échecs inclus) ;
- **Mode sombre** (bascule persistée), skeletons de chargement, transitions de route.
- **CRM** : clients, contacts, opportunités par practice ; création de client et
  changement d'étape d'opportunité (écritures journalisées).
- **Staffing** : consultants (grade, practice, compétences, charge), missions et équipes.
- **Projets** : avancement, météo (vert/orange/rouge), jalons.
- **Facturation** : factures filtrables par statut, totaux, retards mis en évidence.
- **Journal d'audit** : chaque écriture tracée automatiquement — acteur, action, objet,
  **diff des champs**, adresse IP. Les connexions (réussies et échouées) aussi.

## Architecture

**Monolithe modulaire** (Symfony 8, PHP 8.4) + SPA (Vue 3, TypeScript, Tailwind 4) + PostgreSQL 16.

```
synapse/
├── api/                        Symfony 8 — API JSON
│   └── src/Module/
│       ├── Core/               utilisateurs, sécurité, journal d'audit, dashboard
│       ├── Crm/                clients, contacts, opportunités
│       ├── Staffing/           consultants, missions, affectations
│       ├── Project/            suivi de delivery
│       ├── Billing/            factures
│       ├── Hr/                 congés et validations
│       └── Timesheet/          CRA / feuilles de temps
├── app/                        Vue 3 + TS + Vite + Tailwind 4 (SPA)
├── compose.yaml                db (PostgreSQL) · api (FrankenPHP) · web (nginx, port 8082)
└── docs/adr/                   décisions d'architecture
```

Règles de frontière entre modules : tables préfixées, **aucune relation Doctrine
inter-modules** (références par identifiant + nom dénormalisé), noyau partagé minimal.
Détail et justification dans les ADR :

- [ADR 0001 — Choix de la stack](docs/adr/0001-choix-de-la-stack.md)
- [ADR 0002 — Monolithe modulaire](docs/adr/0002-monolithe-modulaire.md)
- [ADR 0003 — Persistance et données de démo](docs/adr/0003-persistance-et-donnees-de-demo.md)

## Sécurité

Pour un outil interne dans une société de cybersécurité, la démo applique par défaut :

- Authentification par session (`json_login`), hashage automatique des mots de passe
  (algorithme au meilleur standard courant via le composant Security) ;
- **Anti brute-force** : `login_throttling` (5 tentatives / identifiant+IP / 15 min) ;
- API intégralement derrière authentification (`access_control`), 401 JSON propres,
  cookie de session `HttpOnly` + `SameSite=Lax` ; **journal d'audit réservé à ROLE_ADMIN**
  (dans l'API comme dans l'extrait du dashboard) ;
- **Content-Security-Policy stricte** (tout est same-origin, y compris les polices) ;
- **Journal d'audit inaltérable côté applicatif** alimenté par listener Doctrine :
  créations, modifications (avec diff), suppressions, connexions et tentatives échouées —
  champs sensibles masqués ;
- Même origine front/API via reverse proxy (pas de CORS ouvert), en-têtes de sécurité
  nginx, IP client restaurée via proxies de confiance (`private_ranges`) ;
- Secrets uniquement par variables d'environnement — les valeurs du `compose.yaml`
  sont des valeurs de démo assumées.

## Qualité

| Vérification | Outil | État |
|---|---|---|
| Analyse statique API | phpstan niveau 6 | 0 erreur |
| Tests fonctionnels API | PHPUnit (auth, RBAC, dashboard, CRM, RH, CRA, marges, audit) | 39 tests |
| Types front | vue-tsc strict | 0 erreur |
| CI | GitHub Actions (api + app + build Docker) | [`.github/workflows/ci.yml`](.github/workflows/ci.yml) |

## Méthode : ingénierie augmentée par IA, avec garde-fous

Ce projet a été construit en **pilotant des agents de code IA (Claude Code)** — c'est
précisément la méthode que je propose d'industrialiser pour l'ERP :

- l'IA produit vite ; **l'ingénieur décide** : architecture, frontières de modules,
  modèle de données et arbitrages sont documentés en ADR *avant* la génération ;
- chaque itération passe par les mêmes garde-fous qu'une équipe : analyse statique,
  tests fonctionnels, revue visuelle, CI ;
- le journal de bord de la conversation fait office de trace de conception.

*L'IA est un interne brillant qui parle avec assurance ; il faut un médecin senior pour
valider le diagnostic.*

## Limites connues (choix de périmètre démo)

Pagination, RBAC fin par module, SSO d'entreprise (OIDC), exports comptables, migrations
versionnées et tests E2E sont volontairement hors périmètre — ils figurent en tête de la
roadmap proposée. Voir aussi [ADR 0003](docs/adr/0003-persistance-et-donnees-de-demo.md).
