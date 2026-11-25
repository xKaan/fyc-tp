# Journal des comportements de l'agent NoteFlow

À remplir pendant l'étape 6 du TP S2.4, une ligne par question posée. Le
journal est un brouillon : notez vite, et surtout ce qui vous surprend. À
l'étape 7, vous en tirez cinq cas de référence dans
[cas-de-reference.md](cas-de-reference.md).

Ce qu'il faut relever se lit en bas de la page de l'assistant : les
sources, la raison d'arrêt et le nombre d'étapes. Le reste se lit dans la
réponse elle-même. Les outils appelés par le modèle ne s'affichent pas
encore : les traces de la séance 3 les montreront, et vous pourrez alors
compléter ce journal.

## Avant de commencer

| Réglage | Valeur |
|---|---|
| `LLM_PROVIDER` |  |
| Modèle (`LLM_OLLAMA_MODEL` ou `LLM_HTTP_MODEL`) |  |
| `LLM_OLLAMA_CONTEXT_LENGTH` |  |
| `NOTEFLOW_AGENT_MAX_STEPS` |  |
| Recherche branchée (mots-clés, plein texte, vectorielle) |  |

Changez de tableau quand vous changez de fournisseur ou de modèle : deux
modèles ne se comparent que sur les mêmes questions.

## Journal

| N° | Connecté en | Question posée | Raison d'arrêt | Étapes | Réponse, en quelques mots | Sources affichées | Ce qui surprend | Le code a-t-il tenu ? |
|---|---|---|---|---|---|---|---|---|
| 1 |  |  |  |  |  |  |  |  |
| 2 |  |  |  |  |  |  |  |  |
| 3 |  |  |  |  |  |  |  |  |
| 4 |  |  |  |  |  |  |  |  |
| 5 |  |  |  |  |  |  |  |  |
| 6 |  |  |  |  |  |  |  |  |
| 7 |  |  |  |  |  |  |  |  |
| 8 |  |  |  |  |  |  |  |  |

Comment remplir les colonnes :

- **Raison d'arrêt** : `answered`, `dont_know`, `awaiting_approval` ou
  `step_limit`, telle qu'affichée.
- **Réponse** : l'idée principale, pas la phrase exacte. Pour une tâche
  proposée, l'action affichée avant « Confirmer ».
- **Sources affichées** : les titres des notes sous la réponse. Notez si
  une note attendue manque, ou si une note d'un projet que vous ne lisez
  pas apparaît (ce serait un bug grave).
- **Ce qui surprend** : un mauvais outil, une citation oubliée, un
  identifiant inventé, une réponse coupée, une langue qui change, une
  réponse juste mais sans source.
- **Le code a-t-il tenu ?** : oui si le garde-fou attendu a joué (le
  « je ne sais pas » à la place d'une citation inventée, la confirmation
  avant la tâche, l'arrêt au plafond), non sinon. Un « non » est un bug à
  corriger avant la séance 3.

Exemple de ligne :

| N° | Connecté en | Question posée | Raison d'arrêt | Étapes | Réponse, en quelques mots | Sources affichées | Ce qui surprend | Le code a-t-il tenu ? |
|---|---|---|---|---|---|---|---|---|
| 0 | Alice | « Qu'a-t-on décidé pour la base de données ? » | `answered` | 2 | PostgreSQL retenu plutôt que MongoDB | Décision : choix de PostgreSQL plutôt que MongoDB | rien | oui |

## Ce que le journal doit vous apprendre

Relisez-le avant l'étape 7 et répondez en une phrase à chaque question :

1. Quelle question a donné un comportement différent d'un fournisseur à
   l'autre ?
2. Quelle erreur du modèle le code a-t-il rattrapée, et par quel contrôle ?
3. Y a-t-il une erreur que le code n'a pas rattrapée ?
