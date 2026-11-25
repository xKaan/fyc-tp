# TP S2.4 : corrigé, état fin S2

Ce document accompagne la branche `fyc-tp-s2-correction`. C'est l'état
du fil rouge à la fin de la séance 2, et le **point de départ du TP S3.4** :
si vous n'avez pas fini, repartez de cette branche.

Tout le diff du TP s'affiche avec :

```bash
git diff fyc-tp-s2..fyc-tp-s2-correction
```

La branche contient deux choses :

1. le corrigé des quatre fichiers de l'énoncé (étapes 2 à 5) ;
2. ce qui fait passer le projet à l'état fin S2 : la **recherche par
   similarité** de S2.3, branchée sur le même port, et les cinq cas de
   référence remplis ([cas-de-reference.md](cas-de-reference.md)).

## Ce que l'on cherchait à obtenir

Trois acquis, dans cet ordre :

1. **Le modèle propose, le code décide.** Les droits, la validation des
   arguments, la confirmation d'une écriture, le plafond d'étapes et le
   contrôle des citations sont dans le code. Le prompt les demande, il ne
   les garantit pas.
2. **Une seule boucle pour trois fournisseurs.** Les écarts de format
   (identifiant d'appel, arguments en chaîne ou en objet) s'arrêtent dans
   les adaptateurs. La boucle ne voit que des `ToolCall`.
3. **La recherche est une pièce qui se change.** Mots-clés ou similarité :
   `chercher_notes` et la boucle ne bougent pas, seule la liaison du port
   change.

## Étape 1 : les trois questions

**Où se décide le fournisseur utilisé par l'agent ?** Dans
`LlmServiceProvider`, par `LLM_PROVIDER`, comme en séance 1.
`AgentServiceProvider` lie `ToolCallingPort` à l'adaptateur déjà choisi pour
`LlmPort` : les trois adaptateurs implémentent les deux ports, il n'y a pas
de second réglage.

**Que se passe-t-il si le modèle demande un outil absent de la `Toolbox` ?**
`find()` renvoie `null`. La boucle renvoie au modèle une erreur qui liste
les outils disponibles, et continue. L'outil n'est jamais exécuté : la
`Toolbox` est une liste blanche, et c'est le code qui la tient.

**Pourquoi le schéma JSON ne suffit-il pas à valider les arguments ?** Le
schéma est une indication envoyée au modèle, pas une contrainte qu'il
respecte : un petit modèle local envoie volontiers un `note_id` en texte ou
oublie un champ obligatoire. Et un schéma ne sait pas dire « une note que
cet utilisateur a le droit de lire » : cela demande la base et l'utilisateur
courant. D'où `Validator::make()` puis la vérification des droits, dans
chaque outil.

## Étape 2 : le filtre par droits

```php
->whereIn('project_id', $user->readableProjectIds())
```

Une ligne, dans la requête. Une note que la base ne renvoie pas ne peut pas
fuiter, ni dans la réponse, ni dans le contexte envoyé au fournisseur.

**Pourquoi une note sans projet ne sort jamais, sans ligne de plus ?** En
SQL, `NULL IN (1, 2)` ne vaut ni vrai ni faux mais `NULL`, et le `WHERE` ne
garde que les lignes où la condition est vraie. Et si l'utilisateur n'a
aucun projet, Laravel traduit `whereIn` sur une liste vide par une condition
toujours fausse : aucune note.

Ce qu'il ne fallait pas faire : récupérer toutes les notes puis filtrer en
PHP (le jour où quelqu'un retire le filtre pour « déboguer », tout sort), ou
écrire « ne montre que les notes du projet de l'utilisateur » dans le
prompt (un modèle peut ignorer une consigne, ou se la faire retirer par une
injection, séance 4).

## Étape 3 : `chercher_notes`

Les points qui comptent dans
`app/Application/Agent/Tools/ChercherNotes.php` :

- la description dit au modèle **quand** appeler l'outil (« avant toute
  réponse sur le contenu des notes »), pas seulement ce qu'il fait ;
- `additionalProperties: false` et une seule propriété obligatoire : moins
  le contrat laisse de liberté, moins le modèle se trompe ;
- les identifiants renvoyés vont dans `sources` : ce sont les seules notes
  que la boucle acceptera en citation ;
- aucun résultat n'est pas une erreur : une liste vide et un message, que le
  modèle transforme en « je ne sais pas ».

L'outil ne filtre rien lui-même : il passe l'utilisateur à la recherche. Le
filtre est à un seul endroit, la requête.

## Étape 4 : `creer_tache` et sa confirmation

`describe()` et `run()` passent par la même méthode privée `check()` :
validation des arguments, existence de la note, droit de la lire. La
première ne fait que décrire, la seconde écrit.

Les deux points à ne pas rater :

```php
if ($note === null || ! $user->canReadNote($note)) {
    throw new InvalidToolArguments("Arguments refusés : la note {$arguments['note_id']} est introuvable.");
}
```

Un seul message pour « n'existe pas » et « existe ailleurs ». Deux messages
différents feraient de l'outil un moyen de sonder les notes des autres
projets, un identifiant après l'autre.

Et `run()` revérifie tout, parce qu'entre la proposition et le clic sur
« Confirmer », l'utilisateur a pu quitter le projet. La confirmation
autorise **cette** action, pas une action dont les conditions auraient
changé.

Ce qui reste à faire, et qui l'est en séance 3 : `creer_tache` ne se
protège pas d'un doublon. Le prompt demande au modèle de vérifier les
tâches ouvertes avec `lister_taches_ouvertes`, mais rien ne l'y oblige, et
un nouvel essai après une panne recréerait la tâche. C'est le rôle de la
clé d'idempotence (S3.1).

## Étape 5 : la boucle

`app/Application/Agent/NoteAgent.php`. Quatre raisons d'arrêt, et pas une
de plus :

| Raison | Quand |
|---|---|
| `answered` | le modèle répond sans appel d'outil, et cite au moins une note, toutes présentes dans `$sources` |
| `dont_know` | le modèle répond sans citation, dit « je ne sais pas », ou cite une note qu'il n'a pas reçue |
| `awaiting_approval` | le modèle demande `creer_tache` : la boucle enregistre l'action et s'arrête |
| `step_limit` | `NOTEFLOW_AGENT_MAX_STEPS` appels au modèle sans réponse finale |

Deux détails :

- une erreur d'outil (inconnu, arguments refusés) **repart au modèle** au
  lieu d'arrêter la boucle : il peut corriger son appel au tour suivant, et
  le plafond d'étapes borne le nombre d'essais ;
- `Message::assistantToolCalls($turn)` est ajouté **avant** les résultats :
  les fournisseurs refusent un résultat d'outil qui ne répond à aucun appel.

Le contrôle des citations est le même principe qu'à l'étape 2 : le prompt
demande de citer, le code vérifie. Une citation inventée, ou d'une note lue
ailleurs que dans les résultats autorisés, devient « je ne sais pas ».

## Au-delà de l'énoncé : la recherche par similarité

C'est ce qui fait de cette branche l'état fin S2 : la recherche vectorielle
de S2.3, écrite sans SDK, derrière le port `NoteSearch`.

| Fichier | Rôle |
|---|---|
| `app/Domain/Ports/EmbeddingPort.php` | le port de vectorisation : des textes en entrée, un vecteur par texte en sortie |
| `app/Infrastructure/Embeddings/` | trois adaptateurs : Ollama (`/api/embed`), en ligne au format OpenAI (`/embeddings`), et une doublure déterministe |
| `database/migrations/..._create_note_embeddings_table.php` | un vecteur par note, de type `vector(768)` sous PostgreSQL, avec le modèle qui l'a produit |
| `app/Infrastructure/Search/NoteIndexer.php` | l'indexation, lancée par `php artisan noteflow:index` |
| `app/Infrastructure/Search/PgvectorNoteSearch.php` | la recherche : distance cosinus `<=>`, droits dans le `WHERE` |

La requête de recherche, en entier :

```php
DB::table('note_embeddings')
    ->join('notes', 'notes.id', '=', 'note_embeddings.note_id')
    ->join('projects', 'projects.id', '=', 'notes.project_id')
    ->whereIn('notes.project_id', $user->readableProjectIds())
    ->where('note_embeddings.model', $this->embeddings->modelName())
    ->selectRaw('note_embeddings.embedding <=> ?::vector as distance', [$vecteur])
    ->orderBy('distance')
    ->limit($limit);
```

Trois choix à retenir :

- **Le même modèle à l'indexation et à la recherche.** Chaque vecteur est
  rangé avec le nom du modèle qui l'a produit, et la recherche ne compare
  que les vecteurs de son modèle. Changer de modèle sans réindexer donne une
  liste vide, pas des résultats faux en silence.
- **Une note, un vecteur.** Les notes font moins de 300 mots : pas de
  découpage. Des documents longs demanderaient une ligne par morceau.
- **Recherche exacte, sans index HNSW.** Pour 26 notes, elle est
  instantanée et donne le recall de référence. L'index approximatif se
  justifie avec des milliers de notes, et se mesure (S2.3).

Une recherche par similarité renvoie toujours les notes « les moins
éloignées », même pour « Quelle est la capitale de l'Australie ? ». Elle ne
sait pas dire « rien » : c'est la boucle qui refuse une réponse sans source
valable (cas de référence 4).

La recherche par mots-clés reste la valeur par défaut, parce qu'elle marche
sur SQLite et sans modèle : c'est elle que vous retrouverez en séance 3 comme
mode sans IA du plan de secours.

### Passer à la recherche vectorielle

Le service `pgsql` (image `pgvector/pgvector:0.8.7-pg18`, épinglée) est dans
`compose.yaml`. Dans `.env`, décommentez le bloc PostgreSQL, puis :

```dotenv
NOTEFLOW_SEARCH=vector
```

Téléchargez le modèle de vectorisation, puis indexez :

```bash
ollama pull nomic-embed-text
./vendor/bin/sail up -d
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan noteflow:index
```

Relancer `noteflow:index` ne recalcule que les notes modifiées. Avec le
fournisseur en ligne, `mistral-embed` produit des vecteurs de dimension
1024 : réglez `LLM_EMBEDDING_DIMENSIONS=1024` **avant** la migration, car la
colonne est créée à cette dimension.

Une correction en passant : sous PostgreSQL, `LIKE` distingue les
majuscules. Le filtre par titre de la séance 1 (`Note::matchingTitle`) passe
donc par `whereLike`, insensible à la casse sur les deux bases, pour que ses
tests restent verts sous PostgreSQL.

## Le journal et les cinq cas de référence

Le modèle de journal est [journal-comportements.md](journal-comportements.md),
et les cinq cas attendus sont dans [cas-de-reference.md](cas-de-reference.md) :
une réponse avec source, une note interdite, une tâche proposée, un « je ne
sais pas », et un cas où le modèle se trompe et où le code tient. Chacun est
relié au test automatique qui vérifie le même garde-fou avec une doublure.

Vos cas n'ont pas à être les mêmes. Ils doivent couvrir des comportements
différents, et chacun doit se vérifier sans lire la réponse mot à mot.

## Point de départ de la séance 3

En S3.4, vous ajouterez à cet agent :

- une trace par requête, avec un span par appel au modèle, par outil et par
  recherche (la vectorisation de la question compte comme un appel au
  fournisseur, avec son coût) ;
- le retry, le coupe-circuit et le repli à trois niveaux : IA en ligne, IA
  locale, puis mode sans IA sur `KeywordNoteSearch` ;
- la clé d'idempotence de `creer_tache` ;
- deux cas de référence tirés des traces.

## Vérifier soi-même

```bash
./vendor/bin/sail artisan test
```

102 tests verts et 4 ignorés sous SQLite : les 91 de l'énoncé, plus 15 pour
la recherche par similarité, dont 4 qui demandent PostgreSQL. Pour les
lancer aussi :

```bash
docker compose exec pgsql createdb -U sail testing
docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql -e DB_DATABASE=testing laravel.test php artisan test
```

Les variables passent par `-e` : `phpunit.xml` force SQLite, et une variable
posée sur votre machine n'entre pas dans le conteneur.

105 tests verts, 1 ignoré (celui qui vérifie le refus de la recherche
vectorielle hors PostgreSQL).

```bash
git diff fyc-tp-s2..fyc-tp-s2-correction -- tests/Tp2/
```

Ne doit rien afficher : les tests de l'énoncé n'ont pas été touchés.

```bash
grep -rn "api.mistral.ai\|11434\|mistral-embed\|nomic-embed" app/ routes/ resources/
```

Ne doit rien afficher non plus : les URL et les noms de modèle restent dans
`config/`.
