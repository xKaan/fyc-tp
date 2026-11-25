---
titre: Bug : doublons de notes après synchronisation
projet: Produit
auteur: tom
tags: bug, synchronisation
---
Étapes pour reproduire :
1. Ouvrir la même note sur deux onglets.
2. Couper la connexion, modifier la note dans les deux onglets.
3. Rétablir la connexion.

Résultat : deux notes identiques au titre près apparaissent. Attendu : une seule note, avec un conflit signalé.

Cause probable : le client renvoie la note quand la première requête n'a pas répondu à temps, et le serveur crée une nouvelle note à chaque envoi.

Piste : rendre la création idempotente avec une clé envoyée par le client.
