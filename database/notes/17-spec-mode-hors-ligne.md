---
titre: Spécification : mode hors ligne (brouillon)
projet: Produit
auteur: tom
tags: specification, hors-ligne
---
Brouillon, à discuter au prochain point hebdo.

L'utilisateur peut écrire une note sans connexion. La note est enregistrée sur son appareil, puis envoyée au serveur au retour de la connexion.

Problème connu : si la même note est modifiée hors ligne sur deux appareils, la synchronisation actuelle crée des doublons au lieu de fusionner. Il faut régler ce bug avant d'ouvrir le mode hors ligne.

Piste : un identifiant de note créé sur l'appareil, pour que le serveur reconnaisse un second envoi de la même note.
