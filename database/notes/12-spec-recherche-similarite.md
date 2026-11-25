---
titre: Spécification : recherche par similarité dans les notes
projet: Produit
auteur: malik
tags: specification, recherche
---
Besoin : retrouver une note à partir d'une question en langage naturel, même si les mots ne sont pas les mêmes (« base de données » doit trouver une note qui parle de PostgreSQL).

Proposition :
1. Garder la recherche plein texte de PostgreSQL, rapide et explicable, qui sert aussi de repli.
2. Ajouter une recherche vectorielle avec pgvector : chaque note est découpée et vectorisée, avec le même modèle à l'indexation et à la recherche.
3. Toujours filtrer par les droits de l'utilisateur dans la requête SQL.

Mesure : cinq questions de référence, et le recall@5 comparé entre les deux recherches avant de choisir.
