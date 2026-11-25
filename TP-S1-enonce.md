# TP S1.3 : un port, trois adaptateurs

**Durée : 55 minutes.** 5 min de vidéo, 45 min de réorganisation, 5 min de
grille comparative.

## Le contexte

Vous reprenez une petite application Laravel de prise de notes. Elle marche.
Les tests passent. Un stagiaire l'a écrite en trois jours, il est parti, et on
vous demande maintenant trois choses :

1. Passer du modèle en ligne à Ollama **sans toucher au code**, parce que le
   client ne veut plus envoyer ses notes dans le cloud.
2. Que l'application **n'affiche plus une erreur 500** quand le service d'IA
   ne répond pas.
3. Pouvoir tester l'application **sans aucun modèle**, ni en ligne ni local.

Aujourd'hui, ces trois demandes coûtent cher. Le but du TP est de comprendre
pourquoi, puis de réorganiser le code pour qu'elles deviennent simples.

## Avant de commencer (5 min)

Regardez la vidéo « Un port, deux fournisseurs, une ligne de config ».

Puis vérifiez votre point de départ :

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
```

Vous devez voir **10 tests verts**. Si ce n'est pas le cas, arrêtez-vous et
appelez l'enseignant.

Les tests ne font aucun appel réseau. Pour utiliser les boutons d'IA dans le
navigateur, il faut en plus qu'Ollama tourne et que son modèle soit chargé en
mémoire : voir la section « le premier appel à Ollama est lent » du
[README](README.md).

## Règles du jeu

1. **Les fichiers `tests/Feature/NoteCrudTest.php` et
   `tests/Feature/NoteAiTest.php` ne se modifient pas.** Pas une ligne. Ils
   décrivent le comportement attendu de l'application, pas son organisation
   interne : ce sont eux qui vous disent que la réorganisation n'a rien cassé.
2. La migration ne se modifie pas non plus. Un changement d'architecture ne
   touche pas la base de données.
3. **Aucun SDK d'IA.** Uniquement le client HTTP de Laravel.
4. À la fin, `./vendor/bin/sail artisan test` est toujours vert.

## Ce qui vous est fourni

Le port est déjà écrit, dans `app/Domain/Ports/` :

```php
interface LlmPort
{
    /** @param non-empty-list<Message> $messages */
    public function complete(array $messages): string;
}
```

- `Message` : un message avec un rôle, créé par `Message::system('...')` ou
  `Message::user('...')`.
- `LlmCallFailed` : l'exception que lève tout adaptateur en cas de panne,
  quelle qu'en soit la cause.

Lisez ces quatre fichiers avant de commencer : ils sont courts, et ils fixent
le vocabulaire de tout le TP.

## Déroulé

### Étape 1 : diagnostiquer (8 min)

Ouvrez `app/Http/Controllers/NoteController.php` et lisez les méthodes
`summarize()` et `suggestTags()`. Notez tout ce qui vous dérange, et pour
chaque point, répondez à la question : **qu'est-ce que cela m'empêche de
faire ?**

Trois questions pour guider la lecture :

- Combien d'endroits faut-il modifier pour changer de fournisseur d'IA ?
- Que se passe-t-il si l'API répond une erreur, ou un JSON inattendu ?
- Comment testeriez-vous le prompt sans faire d'appel réseau ?

### Étape 2 : écrire les trois adaptateurs (17 min)

Dans `app/Infrastructure/Llm/`, écrivez trois classes qui implémentent
`LlmPort` :

| Adaptateur | Ce qu'il fait |
|---|---|
| `HttpLlmAdapter` | appelle `POST {base_url}/chat/completions` au format OpenAI, que Mistral accepte aussi ; la réponse est dans `choices.0.message.content` |
| `OllamaAdapter` | appelle `POST {host}/api/chat` d'Ollama avec `stream: false` ; la réponse est dans `message.content` |
| `FakeLlmAdapter` | la **doublure** : aucune requête, une réponse fixe, et la liste des messages reçus dans une propriété publique `$calls` ; une méthode `failing()` crée une doublure qui lève `LlmCallFailed` |

Chaque adaptateur reçoit son URL, son modèle et son délai d'attente par son
constructeur. C'est le seul endroit du projet où l'on trouve un
`Http::post()`.

Les deux adaptateurs réseau ont trois gardes que le code de départ n'a pas :
un `timeout()`, une erreur HTTP transformée en `LlmCallFailed`, et une
vérification de la forme de la réponse avant de la lire.

Ollama expose aussi une API compatible OpenAI (`/v1/chat/completions`). On
pourrait donc le brancher sur `HttpLlmAdapter`. On garde un adaptateur à part
parce que le contrat n'est pas tout à fait le même : Ollama ne prend pas en
charge tous les paramètres d'OpenAI, et sa fenêtre de contexte (4096 tokens
par défaut) se règle de son côté.

### Étape 3 : sortir le cœur métier et brancher par la configuration (12 min)

1. Créez deux cas d'usage dans `app/Application/` : `SummarizeNote` et
   `SuggestTags`. Chacun reçoit un `LlmPort` par son constructeur, envoie la
   consigne en `Message::system()` et la note en `Message::user()`, puis
   enregistre le résultat. Le plafond de cinq tags reste vérifié par le code,
   pas confié au modèle.
2. Sortez les prompts du code : un fichier par prompt et par version,
   `resources/prompts/summarize-note/v1.md` et
   `resources/prompts/suggest-tags/v1.md`. La version servie se choisit dans
   la configuration.
3. Créez `config/llm.php` : le fournisseur (`LLM_PROVIDER`, qui vaut `fake`
   par défaut), puis l'URL, la clé, le modèle et le délai de chaque
   adaptateur, puis la version de chaque prompt.
4. **Épinglez le modèle** : un identifiant daté, pas un alias comme
   `mistral-small-latest`, qui change de modèle sans prévenir.
5. Dans un service provider, liez `LlmPort` à l'adaptateur choisi par
   `config('llm.provider')`.
6. Le contrôleur ne fait plus que du HTTP : recevoir la requête, appeler le cas
   d'usage, attraper `LlmCallFailed` pour afficher un message lisible,
   renvoyer une réponse.

Deux pièges :

- `env()` ne s'appelle jamais ailleurs que dans un fichier de `config/`.
  Cherchez pourquoi : la réponse tient au cache de configuration.
- Le contenu de la note ne doit jamais être concaténé dans la consigne. Deux
  messages, deux rôles.

### Étape 4 : mettre la doublure au travail (8 min)

Écrivez `tests/Feature/NoteAiFailureTest.php`. Remplacez le fournisseur par
une doublure en panne :

```php
$this->app->instance(LlmPort::class, FakeLlmAdapter::failing());
```

Puis vérifiez que l'utilisateur voit un message lisible plutôt qu'une page
d'erreur, et que la note n'est pas modifiée.

Ajoutez un test unitaire de `SummarizeNote` avec un `FakeLlmAdapter` : il
vérifie, sans réseau, que la consigne part en rôle `system` et la note en rôle
`user`.

Ces tests étaient **impossibles à écrire** sur le code de départ. Prenez une
minute pour comprendre pourquoi votre réorganisation les rend possibles. La
doublure vous servira dans tous les TP suivants.

### Étape 5 : comparer (5 min)

Remplissez la colonne « Mon code » de
[grille-comparative.md](grille-comparative.md), puis lisez la grille remplie
sur la branche corrigée : ce que le Laravel AI SDK et Spring AI imposent, et
ce qui reste vrai partout.

## Ce qui est évalué

- Les 10 tests de départ passent toujours, sans avoir été modifiés.
- Basculer `LLM_PROVIDER` entre `fake`, `ollama` et `http` ne demande aucune
  modification de code.
- Aucune URL, clé ou nom de modèle en dehors de `config/`, et le modèle en
  ligne est épinglé.
- Les prompts sont dans des fichiers versionnés.
- Le test de panne passe avec la doublure.
- La colonne « Mon code » de la grille est remplie.

## Si vous avez fini en avance

Le contrôleur de départ contient une quinzaine d'autres défauts : validation
écrite à la main, logique métier dans les vues, notes chargées en mémoire
avant d'être filtrées en PHP, suppression par une requête GET, données
utilisateur envoyées dans les logs... Corrigez-en autant que vous pouvez, en
gardant les tests verts.
