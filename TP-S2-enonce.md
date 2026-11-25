# TP S2.4 : l'agent NoteFlow

**Durée : 65 minutes.** 5 min de présentation du cas, 50 min de
construction, 10 min pour écrire vos cinq premiers cas de référence.

## Le contexte

L'application de notes de la séance 1 est devenue **NoteFlow** : l'outil
d'une équipe produit de six personnes, qui y range ses réunions, ses
décisions et ses spécifications, projet par projet. Vingt-six notes plus
tard, plus personne ne retrouve rien, et des actions décidées en réunion
restent sans suite.

On vous demande un assistant qui :

1. **répond à une question** en s'appuyant sur les notes, et cite la note
   d'où vient sa réponse, ou dit « je ne sais pas » ;
2. **ne lit que les notes que l'utilisateur a le droit de lire** ;
3. **propose de créer une tâche** quand une note contient une action, mais
   ne la crée qu'après une confirmation qui montre l'action exacte ;
4. **s'arrête toujours**, même si le modèle tourne en rond ;
5. marche **avec Ollama comme avec le modèle en ligne**, sans toucher au
   cœur métier.

Ce dépôt sert jusqu'à la séance 5 : en séance 3 vous le rendrez fiable et
observable, en séance 4 vous écrirez sa suite de tests et ses défenses
contre l'injection.

## Avant de commencer (5 min)

Lisez la fiche du fil rouge (cas, outils, critères de réussite), puis
installez le dépôt :

```bash
git switch fyc-tp-s2
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan test
```

Vous devez voir **60 tests verts et 31 rouges**. Les verts couvrent ce qui
est fourni (la séance 1, les adaptateurs, l'outil d'exemple). Les rouges
sont dans `tests/Tp2/` : ils décrivent ce que vous allez écrire. Si le
compte n'est pas celui-là, arrêtez-vous et appelez l'enseignant.

Le seeder a créé six personnes, trois projets et les 26 notes de
`database/notes/`. Retenez deux personnes :

| Personne | Projets lisibles |
|---|---|
| Alice Martin, responsable produit | Produit, Équipe |
| Chloé Petit, exploitation | Équipe, Infra |

Alice ne doit jamais voir une note du projet Infra.

Pour essayer l'assistant dans le navigateur (étape 6), chauffez le modèle
d'Ollama comme en séance 1 (voir le [README](README.md)).

## Règles du jeu

1. **Les tests de `tests/` ne se modifient pas**, ni ceux de la séance 1,
   ni ceux de `tests/Tp2/`. Ils décrivent le comportement attendu.
2. **Aucun SDK d'IA.** Les adaptateurs sont fournis et n'utilisent que le
   client HTTP de Laravel.
3. Vous n'écrivez que dans quatre fichiers : `KeywordNoteSearch`,
   `ChercherNotes`, `CreerTache` et `NoteAgent`. Le reste est fourni.
4. À la fin, `./vendor/bin/sail artisan test` est entièrement vert.

## Ce qui vous est fourni

Lisez ces fichiers dans cet ordre, ils sont courts (6 min, comptées dans
l'étape 1).

| Fichier | Ce qu'il contient |
|---|---|
| `app/Domain/Ports/ToolCallingPort.php` | le port d'un tour de boucle : `next($messages, $tools)` renvoie un `AssistantTurn` |
| `app/Domain/Ports/AssistantTurn.php`, `ToolCall.php` | la réponse du modèle, normalisée : du texte, ou des appels d'outils avec leurs arguments décodés |
| `app/Domain/Ports/Message.php` | deux constructeurs de plus : `assistantToolCalls()` et `toolResult()` |
| `app/Infrastructure/Llm/` | les trois adaptateurs, qui implémentent aussi `ToolCallingPort` ; la doublure a une méthode `scripted()` |
| `app/Application/Agent/Tool.php`, `RequiresApproval.php` | le contrat d'un outil, et celui d'un outil qui écrit |
| `app/Application/Agent/Tools/ListerTachesOuvertes.php` | **l'outil d'exemple**, entièrement écrit : votre modèle pour les deux autres |
| `app/Application/Agent/ToolResult.php`, `Toolbox.php`, `AgentAnswer.php`, `Citations.php` | le résultat d'un outil, la liste blanche des outils, ce que la boucle renvoie, la lecture des citations `[note:12]` |
| `app/Application/Agent/ConfirmPendingAction.php` | l'exécution d'une action après confirmation |
| `resources/prompts/note-agent/v1.md` | la consigne de l'agent, versionnée |

Les adaptateurs ont déjà normalisé les écarts entre fournisseurs : au
format OpenAI, un appel d'outil a un identifiant et des arguments en chaîne
JSON ; chez Ollama, souvent pas d'identifiant et des arguments en objet.
Votre boucle ne voit que des `ToolCall`. C'est le port de la séance 1 qui
paie : vous écrivez un seul agent pour trois fournisseurs.

## Déroulé

### Étape 1 : lire le squelette (6 min)

Lisez les fichiers du tableau ci-dessus, en commençant par
`ListerTachesOuvertes`. Repérez ses quatre temps : le contrat, la
validation des arguments par le code, les droits dans la requête, un
résultat court.

Puis répondez à ces trois questions, sur papier :

- Où se décide le fournisseur utilisé par l'agent ?
- Que se passe-t-il si le modèle demande un outil qui n'est pas dans la
  `Toolbox` ?
- Pourquoi le schéma JSON d'un outil ne suffit-il pas à valider ses
  arguments ?

### Étape 2 : filtrer la recherche par droits (5 min)

Ouvrez `app/Infrastructure/Search/KeywordNoteSearch.php`. La recherche par
mots-clés est écrite, mais elle lit toutes les notes. Ajoutez le filtre à
l'endroit marqué `TODO étape 2`.

Le filtre se fait **dans la requête**, pas après coup dans une boucle PHP,
et jamais par une consigne dans le prompt : un modèle peut ignorer une
consigne, il ne peut pas lire une ligne que la base ne lui a pas renvoyée.
`User::readableProjectIds()` vous donne les projets de l'utilisateur.

Test : `./vendor/bin/sail artisan test tests/Tp2/RechercheDroitsTest.php`

Un des tests vérifie qu'une note sans projet ne sort jamais. Votre filtre
le garantit-il sans ligne de plus ? Cherchez pourquoi.

### Étape 3 : l'outil `chercher_notes` (8 min)

Dans `app/Application/Agent/Tools/ChercherNotes.php`, écrivez :

1. `definition()` : le nom `chercher_notes`, une description qui dit au
   modèle **quand** s'en servir, et le schéma JSON d'un objet avec un
   argument obligatoire `question` de type `string`.
2. `run()` : validez les arguments avec `Validator::make()`, comme dans
   l'outil d'exemple, puis appelez `$this->search->search()` avec
   l'utilisateur, et renvoyez au plus 5 notes (identifiant, titre, projet,
   extrait).

Les identifiants des notes renvoyées vont dans le deuxième argument de
`ToolResult::ok()`, `sources` : ce sont **les seules notes que le modèle
aura le droit de citer**. Aucun résultat n'est pas une erreur : renvoyez une
liste vide et un message.

Test : `tests/Tp2/ChercherNotesTest.php`

### Étape 4 : l'outil `creer_tache` et sa confirmation (10 min)

`CreerTache` implémente `RequiresApproval` : la boucle ne l'exécute jamais
directement. Écrivez :

1. `definition()` : `titre` (texte) et `note_id` (entier) obligatoires,
   `echeance` facultative au format `AAAA-MM-JJ`.
2. `describe()` : validez les arguments, vérifiez que la note existe **et**
   que l'utilisateur a le droit de la lire (`User::canReadNote()`), puis
   renvoyez une phrase qui décrit l'action exacte : titre, projet, note
   source, échéance. **Rien n'est écrit en base.**
3. `run()` : refaites les mêmes vérifications, puis créez la tâche.

Deux points à ne pas rater :

- Une note qui existe dans un autre projet et une note qui n'existe pas
  donnent **le même message d'erreur**. Sinon, l'erreur renvoyée au modèle
  révèle qu'une note existe là où l'utilisateur n'a pas accès.
- `run()` revérifie les droits. Entre la proposition et le clic sur
  « Confirmer », l'utilisateur a pu quitter le projet.

Test : `tests/Tp2/CreerTacheTest.php`

### Étape 5 : la boucle (15 min)

C'est le cœur du TP. Dans `app/Application/Agent/NoteAgent.php`, écrivez
`ask()` en suivant les commentaires :

```php
$messages = [Message::system($this->prompt->text), Message::user($question)];
$sources = [];

for ($step = 1; $step <= $this->maxSteps; $step++) {
    $turn = $this->llm->next($messages, $this->tools->definitions());

    if (! $turn->hasToolCalls()) {
        // le modèle a fini : vérifier sa réponse (voir plus bas)
    }

    $messages[] = Message::assistantToolCalls($turn);

    foreach ($turn->toolCalls as $call) {
        // à vous
    }
}

return AgentAnswer::stepLimitReached($this->maxSteps);
```

Pour chaque appel d'outil :

| Cas | Ce que fait la boucle |
|---|---|
| l'outil n'est pas dans la `Toolbox` | renvoie au modèle une erreur qui liste les outils disponibles, et continue |
| l'outil lève `InvalidToolArguments` | renvoie le message au modèle, qui peut corriger au tour suivant |
| l'outil est un `RequiresApproval` | appelle `describe()`, enregistre `PendingAction::propose()`, et **s'arrête** sur `AgentAnswer::awaitingApproval()` |
| sinon | exécute `run()`, ajoute ses sources, renvoie le résultat avec `Message::toolResult()` |

Quand le modèle répond sans appel d'outil, la réponse n'est acceptée que si
elle cite au moins une note `[note:ID]`, et **seulement des notes présentes
dans `$sources`**. Dans tous les autres cas (le modèle dit « je ne sais
pas », ne cite rien, ou cite une note qu'il n'a pas reçue), renvoyez
`AgentAnswer::dontKnow()`. `Citations::extract()` lit les citations pour
vous.

Ce dernier contrôle est le même principe qu'à l'étape 2 : le prompt
**demande** de citer, le code **vérifie**. Une note inventée, ou lue
ailleurs que dans les résultats autorisés, ne passe pas.

Test : `tests/Tp2/NoteAgentTest.php`, puis toute la suite :

```bash
./vendor/bin/sail artisan test
```

### Étape 6 : essayer pour de vrai et tenir un journal (6 min)

Ouvrez http://localhost/connexion, connectez-vous en Alice, puis allez sur
« Assistant ». Posez au moins ces questions, avec `LLM_PROVIDER=ollama` :

1. « Qu'a-t-on décidé pour la base de données ? »
2. « Comment obtenir un accès à la base de données de production ? »
3. « Crée une tâche pour la grille tarifaire du client pilote. »
4. « Quelle est la capitale de l'Australie ? »

Refaites la question 2 en Chloé. Si vous avez une clé, refaites la série
avec `LLM_PROVIDER=http` : une ligne de `.env`, aucune ligne de code.

Pendant ce temps, **notez dans le journal**
[journal-comportements.md](journal-comportements.md) chaque comportement
observé : la réponse, les sources, la raison d'arrêt affichée en bas, le
nombre d'étapes, et tout ce qui vous surprend. Un petit modèle local se trompe souvent : il appelle
le mauvais outil, oublie de citer, invente un identifiant. C'est
exactement ce qu'il faut noter : le code doit tenir même quand le modèle se
trompe.

Si une question échoue parce que la réponse est coupée, regardez la taille
de contexte d'Ollama (`LLM_OLLAMA_CONTEXT_LENGTH`, chapitre 00) : les
définitions d'outils et les résultats de recherche prennent de la place.
C'est un réglage d'adaptateur, pas du cœur métier.

### Étape 7 : vos cinq premiers cas de référence (10 min)

Transformez votre journal en cinq cas dans
[cas-de-reference.md](cas-de-reference.md) : une entrée, la personne
connectée, le comportement attendu, et ce que vous avez observé avec chaque
fournisseur.

Choisissez des cas qui couvrent des comportements différents : une réponse
avec source, un « je ne sais pas », une note interdite, une tâche proposée,
un cas où le modèle s'est trompé. Un comportement attendu se vérifie sans
lire la réponse mot à mot (« cite la note 8 », « ne mentionne aucune note
Infra », « aucune tâche créée sans confirmation ») : rien ne garantit qu'un
modèle réponde deux fois pareil.

Ces cas ne sont pas jetés : vous en ajouterez deux tirés des traces en
séance 3, et ils deviendront des tests en séance 4.

Versionnez votre travail :

```bash
git add -A && git commit -m "TP S2.4 : agent NoteFlow"
```

## Ce qui est évalué

- Toute la suite est verte, sans qu'aucun test ait été modifié.
- Pour « Qu'a-t-on décidé pour la base de données ? », `chercher_notes`
  renvoie la note « Décision : choix de PostgreSQL plutôt que MongoDB », et
  seulement des notes que l'utilisateur a le droit de lire.
- Le filtre par droits est dans la requête SQL.
- L'agent s'arrête au plafond d'étapes (`NOTEFLOW_AGENT_MAX_STEPS`).
- Une tâche n'est créée qu'après une confirmation qui montre l'action
  exacte.
- La réponse cite la note source ou dit « je ne sais pas ».
- Passer d'Ollama au modèle en ligne ne demande que `LLM_PROVIDER`.
- Cinq cas de référence sont écrits.

## Si vous avez fini en avance

- Branchez votre recherche de S2.3 (plein texte ou pgvector) à la place de
  `KeywordNoteSearch` : une ligne dans `AgentServiceProvider`. Votre
  `RechercheDroitsTest` passe-t-il toujours ? Les tests tournent sur SQLite :
  pour la recherche PostgreSQL, il faudra lancer ces tests sur la base
  PostgreSQL du chapitre 00.
- Les pages de notes de la séance 1 n'ont aucun contrôle de droits : Alice
  peut ouvrir `/notes/24` à la main. Appliquez la même règle aux pages, en
  gardant les tests de la séance 1 verts.
- Ajoutez l'outil `etoffer_note` de la fiche du fil rouge. Doit-il demander
  une confirmation ? Justifiez.
- Le plafond d'étapes compte les appels au modèle. Ajoutez un budget de
  tokens : qu'est-ce que l'adaptateur devrait renvoyer de plus ?
