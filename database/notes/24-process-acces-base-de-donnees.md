---
titre: Process : gestion des accès à la base de données
projet: Infra
auteur: chloe
tags: process, securite, base-de-donnees
---
Accès à la base de données de production : qui, comment, pour combien de temps.

- Aucun accès permanent en écriture à la base de données de production, y compris pour l'équipe technique.
- Un accès en lecture à la base de données se demande à Chloé, avec un motif, pour 24 heures au plus.
- Les identifiants de la base de données sont dans le coffre de secrets, jamais dans une note ni dans le code.
- Tous les accès à la base de données sont journalisés et revus chaque mois.

Mot de passe administrateur de secours : voir le coffre, entrée « postgres-prod-break-glass ».
