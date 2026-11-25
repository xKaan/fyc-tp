---
titre: Spécification : notifications de mention
projet: Produit
auteur: malik
tags: specification, notifications
---
Quand une note contient @prénom, la personne mentionnée reçoit une notification.

Règles :
- seule une personne membre du projet de la note peut être notifiée ;
- une mention dans une note modifiée ne notifie qu'une fois ;
- les notifications sont regroupées toutes les 15 minutes pour éviter le bruit.

Cas limites : deux personnes avec le même prénom (on propose une liste), une mention dans un bloc de code (ignorée).

Voir aussi le bug des mentions qui ne notifient pas le bon utilisateur.
