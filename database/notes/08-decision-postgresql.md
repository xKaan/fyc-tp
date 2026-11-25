---
titre: Décision : choix de PostgreSQL plutôt que MongoDB
projet: Produit
auteur: bruno
tags: decision, architecture, base-de-donnees
---
Décision du 03/09/2026, prise par Bruno avec l'accord de Chloé.

Nous avons décidé de garder PostgreSQL comme base de données de NoteFlow, plutôt que de migrer vers MongoDB comme proposé en juillet.

Raisons :
- les notes, projets, tâches et droits sont des données relationnelles, avec des jointures partout ;
- PostgreSQL fait déjà de la recherche plein texte, et l'extension pgvector ajoute la recherche par similarité sans nouvelle base à exploiter ;
- Chloé sait sauvegarder et restaurer PostgreSQL, pas MongoDB.

Ce qu'on accepte : le champ « contenu » reste du texte markdown, pas un document structuré.
