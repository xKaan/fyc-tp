---
titre: Spécification : historique des versions d'une note
projet: Produit
auteur: tom
tags: specification, historique
---
Chaque modification d'une note crée une version. L'utilisateur peut afficher les versions, comparer deux versions et restaurer une version ancienne.

Règles :
- on garde toutes les versions des 90 derniers jours, puis une par semaine ;
- restaurer une version crée une nouvelle version, on ne réécrit jamais l'historique ;
- une note supprimée reste restaurable 30 jours (voir le runbook de restauration).

Estimation : deux sprints, dont un pour la comparaison de versions.
