---
titre: Bug : mentions qui ne notifient pas le bon utilisateur
projet: Produit
auteur: malik
tags: bug, notifications
---
Étapes pour reproduire : dans une note du projet Produit, écrire « @Tom » alors que deux utilisateurs s'appellent Tom dans l'entreprise.

Résultat : le premier Tom trouvé en base est notifié, même s'il n'est pas membre du projet.

Gravité : haute. Une personne extérieure au projet reçoit l'extrait d'une note qu'elle n'a pas le droit de lire.

Correction prévue : chercher la personne mentionnée seulement parmi les membres du projet, conformément à la spécification des notifications.
