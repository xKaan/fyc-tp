---
titre: Incident : lenteur de recherche au-delà de 500 notes
projet: Infra
auteur: chloe
tags: incident, recherche, performance
---
Incident du 02/11/2026, signalé par le client pilote.

Symptôme : la recherche met plus de 4 secondes pour les équipes qui ont plus de 500 notes.

Cause : la recherche chargeait toutes les notes du projet en mémoire avant de filtrer en PHP, au lieu de filtrer dans la requête SQL.

Correction : filtre dans la requête et index sur le titre. Temps de réponse ramené sous 200 ms.

Suite : prévoir un index adapté avant d'activer la recherche vectorielle.
