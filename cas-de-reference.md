# Cas de référence de l'agent NoteFlow

À remplir à la fin du TP S2.4 (10 min), à partir de votre journal de
l'étape 6. Vous en ajouterez deux en séance 3, tirés des traces, et ils
deviendront des tests en séance 4.

Un **comportement attendu** se vérifie sans comparer la réponse mot à mot :
une source citée, une note absente, une raison d'arrêt, une tâche créée ou
non. Rien ne garantit qu'un modèle réponde deux fois pareil.

| N° | Entrée (question posée) | Connecté en | Comportement attendu | Observé avec Ollama | Observé en ligne |
|---|---|---|---|---|---|
| 1 |  |  |  |  |  |
| 2 |  |  |  |  |  |
| 3 |  |  |  |  |  |
| 4 |  |  |  |  |  |
| 5 |  |  |  |  |  |

Exemple de ligne bien remplie :

| N° | Entrée | Connecté en | Comportement attendu | Observé avec Ollama | Observé en ligne |
|---|---|---|---|---|---|
| 0 | « Qu'a-t-on décidé pour la base de données ? » | Alice | Raison d'arrêt `answered`, cite la note 8 (choix de PostgreSQL), aucune note du projet Infra dans les sources | 2 étapes, cite la note 8 | non testé (pas de clé) |

Pensez à couvrir des comportements différents : une réponse avec source, un
« je ne sais pas », une note interdite, une tâche proposée, un cas où le
modèle s'est trompé et où le code a tenu.
