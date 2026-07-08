# ADR 0002 — Architecture : monolithe modulaire à frontières explicites

Date : 2026-07-08 · Statut : acceptée

## Contexte

L'ERP couvre quatre domaines métier (CRM, Staffing, Projets, Facturation) appelés à
évoluer indépendamment. Un seul développeur au démarrage : les microservices sont
exclus d'emblée (coût d'exploitation démesuré), mais un monolithe « plat » deviendrait
un plat de spaghetti dès l'arrivée d'autres développeurs.

## Décision

Un **monolithe modulaire** : `src/Module/{Core, Crm, Staffing, Project, Billing}`.

Règles de frontière :

1. **Chaque module possède ses entités, contrôleurs et tables** (préfixe SQL par module :
   `crm_client`, `staffing_mission`, `billing_invoice`…).
2. **Aucune relation Doctrine entre modules.** Les références inter-modules passent par
   identifiant + nom dénormalisé (`Mission.clientId` / `Mission.clientName`), jamais par
   jointure objet. Un module peut être extrait sans détricoter l'ORM.
3. **`Core` est le noyau partagé** : utilisateurs, sécurité, journal d'audit, vocabulaire
   commun (enum `Practice`).
4. **Lectures transverses assumées** : le dashboard lit les données de tous les modules
   (CQRS allégé) ; les écritures, elles, restent strictement dans leur module.

## Justification

- La modularité est **une discipline de code, pas une infrastructure** : elle prépare
  l'extraction (si un jour nécessaire) sans en payer le prix aujourd'hui.
- Les frontières par identifiants forcent à expliciter les contrats entre domaines —
  exactement ce qui manque aux ERP légacy « lourds, rigides et datés ».
- Un nouveau développeur peut être responsabilisé sur un module sans connaître les autres.

## Conséquences

- Légère dénormalisation (noms clients copiés) : acceptable, et cohérente avec un futur
  passage en événements (`ClientRenamed` → mise à jour des projections).
- La composition (fixtures, dashboard) vit au niveau racine, au-dessus des modules.
