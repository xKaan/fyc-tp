---
titre: Incident : perte de mise en forme à l'export
projet: Infra
auteur: chloe
tags: incident, export
---
Incident du 08/10/2026, de 17 h 40 à 18 h 30.

Symptôme : les notes exportées en HTML perdent les tableaux et les listes imbriquées.

Cause : la mise en production du vendredi a mis à jour la bibliothèque de rendu markdown, qui a changé son traitement des tableaux.

Correction : retour à la version précédente de la bibliothèque, puis version épinglée dans le fichier de dépendances.

À retenir : une dépendance qui change de comportement sans prévenir, c'est un déploiement qu'on ne contrôle pas. On épingle les versions.
