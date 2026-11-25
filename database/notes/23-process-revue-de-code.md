---
titre: Process : revue de code avant mise en production
projet: Infra
auteur: bruno
tags: process, qualite
---
Toute modification passe par une demande de fusion relue par une autre personne que son auteur.

La personne qui relit vérifie :
- que les tests passent dans la CI, et qu'un test couvre le changement ;
- qu'aucune clé, mot de passe ou URL de production n'est dans le code ;
- que les migrations de base de données sont réversibles.

Mise en production : du lundi au jeudi, et le vendredi avant 14 h seulement (décision de la rétrospective du sprint 12).
