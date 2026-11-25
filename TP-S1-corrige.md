# TP S1.3 : corrigé

Ce document accompagne la branche `fyc-tp-s1-correction`. Tout le diff du TP
s'affiche avec :

```bash
git diff fyc-tp-s1..fyc-tp-s1-correction
```

## Ce que l'on cherchait à obtenir

Quatre acquis, dans cet ordre :

1. Un appel à un service externe imprévisible se cache derrière un contrat
   défini par l'application.
2. Le choix de l'implémentation est une décision de configuration, pas de
   code.
3. Une doublure branchée sur ce contrat rend testable ce qui ne l'était pas.
4. Le prompt et le modèle sont de la configuration versionnée, au même titre
   que le reste.

Et un cinquième, plus fin : savoir où **ne pas** poser de frontière.

## Le diagnostic

`summarize()` et `suggestTags()` faisaient chacune une soixantaine de lignes,
avec le même squelette copié-collé : lecture de la clé, `if` sur le
fournisseur, `Http::post()`, lecture de la réponse sans garde, persistance,
redirection. Deux fois le même bloc, à un prompt près.

**Combien d'endroits faut-il modifier pour changer de fournisseur ?** Deux
méthodes, quatre branches `if/else`. Et comme c'est du code, il faut
redéployer : une décision d'exploitation coûtait une livraison.

**Que se passe-t-il si l'API répond une erreur, ou un JSON inattendu ?**

```php
$resume = $reponse->json()['message']['content'];
```

Sur un corps d'erreur, cette ligne lève une erreur PHP et l'utilisateur voit
une page 500. Il n'y avait pas de `timeout()` non plus : une API lente
bloquait la requête jusqu'à l'expiration du serveur web.

**Comment tester le prompt sans appel réseau ?** On ne peut pas : il est
construit dans la méthode qui fait l'appel. On ne peut tester que l'ensemble,
avec `Http::fake()`, donc en connaissant l'URL et le format de réponse du
fournisseur. Le fournisseur entre dans le test.

## Le port

```php
interface LlmPort
{
    /** @param non-empty-list<Message> $messages @throws LlmCallFailed */
    public function complete(array $messages): string;
}
```

La forme « liste de messages avec un rôle » est celle de toutes les API de
modèles actuelles. Elle tient jusqu'à la boucle d'agent de la séance 2, où
l'historique et les résultats d'outils viendront s'ajouter à la liste sans
changer la signature.

Lisez surtout ce qui **n'y est pas** : pas de modèle, pas de température, pas
de clé, pas de nom de fournisseur, pas de tableau JSON. `Message` est un type
de l'application, pas le format d'un fournisseur :

```php
$this->llm->complete([
    Message::system($this->prompt->text),
    Message::user($note->title."\n".$note->content),
]);
```

La consigne et le contenu de la note partent dans deux messages distincts.
C'est la condition de toute défense contre l'injection de prompt, sans en
être une suffisante : la séance 4 y revient.

## Les trois adaptateurs

| Adaptateur | Point d'entrée | Où est la réponse |
|---|---|---|
| `HttpLlmAdapter` | `POST {base_url}/chat/completions` | `choices.0.message.content` |
| `OllamaAdapter` | `POST {host}/api/chat`, `stream: false` | `message.content` |
| `FakeLlmAdapter` | aucun | réponse fixe |

`HttpLlmAdapter` vise le format OpenAI, que Mistral accepte : changer de
fournisseur compatible, c'est changer l'URL, la clé et le modèle, pas la
classe.

Ollama expose aussi `/v1/chat/completions`. On garde pourtant un adaptateur à
part, parce que le contrat n'est pas exactement le même : Ollama ne prend pas
en charge `tool_choice`, `logit_bias` ni `n`, et sa fenêtre de contexte vaut
4096 tokens par défaut. `OllamaAdapter` peut la régler appel par appel
(`num_ctx`, variable `LLM_OLLAMA_CONTEXT_LENGTH`) ; sinon, c'est la valeur du
serveur, réglée au chapitre 00 par `OLLAMA_CONTEXT_LENGTH`. Le port, lui, ne
change pas : une ligne de configuration change l'adaptateur, pas le contrat.

Chaque adaptateur réseau ajoute trois gardes que le code de départ n'avait
pas :

```php
Http::timeout($this->timeout)                       // 1. on n'attend pas indéfiniment

if ($reponse->failed()) {                           // 2. une erreur HTTP est une panne
    throw LlmCallFailed::rejected('ollama', $reponse->status());
}

$texte = $reponse->json('message.content');
if (! is_string($texte) || trim($texte) === '') {   // 3. la forme est vérifiée
    throw LlmCallFailed::unexpectedPayload('ollama');
}
```

Une seule exception, `LlmCallFailed`, pour tous les cas, et elle appartient au
port : le contrôleur l'attrape sans rien savoir du fournisseur. Il n'aurait
rien fait de différent pour un timeout ou un quota dépassé. La séance 3, elle,
distinguera les erreurs qui valent un nouvel essai.

**La doublure** est le troisième adaptateur. Elle enregistre les messages
reçus, renvoie une réponse fixe, et `FakeLlmAdapter::failing()` simule une
panne. C'est aussi le fournisseur par défaut (`LLM_PROVIDER=fake`) :
l'application démarre sans clé ni modèle local, et la CI de la séance 5 en
dépend.

## Les cas d'usage

```php
final class SummarizeNote
{
    public function __construct(
        private readonly LlmPort $llm,
        private readonly Prompt $prompt,
    ) {}

    public function __invoke(Note $note): void
    {
        $note->summary = $this->llm->complete([
            Message::system($this->prompt->text),
            Message::user($note->title."\n".$note->content),
        ]);

        $note->save();
    }
}
```

Pas une ligne de HTTP, pas un nom de fournisseur, pas un chemin de fichier. Le
plafond de cinq tags vit dans `SuggestTags` : le prompt le demande, mais c'est
le code qui le garantit. On ne confie pas une règle métier au modèle.

## Le prompt et le modèle, de la configuration versionnée

Les consignes sont sorties du code :

```text
resources/prompts/summarize-note/v1.md
resources/prompts/suggest-tags/v1.md
```

La version servie se choisit dans `config/llm.php` (`llm.prompts`). Changer
une consigne de fond, c'est créer `v2.md` et changer une valeur ; revenir en
arrière, c'est remettre `v1`. Le `Prompt` garde son nom et sa version : en
séance 3, ils iront dans chaque trace, pour savoir quelle version a produit
quelle réponse.

Le modèle en ligne est épinglé : `mistral-small-2603` et non
`mistral-small-latest`. Un alias change de modèle sans prévenir, et une mise à
jour du modèle est un déploiement que l'on ne contrôle pas. Épingler ne
protège pas de tout pour autant : un modèle daté finit par être retiré (c'est
déjà le cas de `mistral-small-2506`), d'où la séance 5.

## La configuration et le configurateur

```php
// config/llm.php
'provider' => env('LLM_PROVIDER', 'fake'),
```

`LlmServiceProvider` lit cette valeur, lie `LlmPort` à l'adaptateur
correspondant, et donne à chaque cas d'usage son prompt dans la version
configurée. C'est le seul `match` du projet sur le nom d'un fournisseur.

> Changer de fournisseur coûte une ligne de `.env` et zéro ligne de code.

**Pourquoi `env()` ne s'appelle jamais hors de `config/`.** En production, on
lance `php artisan config:cache` : Laravel compile tous les fichiers de
`config/` en un seul et **cesse de lire le `.env`**. Un `env()` placé ailleurs
renvoie alors `null`. Le code de départ aurait basculé en silence sur sa valeur
par défaut en production, un bug qui ne se voit jamais en local.

## Les tests que l'ancienne architecture interdisait

```php
$this->app->instance(LlmPort::class, FakeLlmAdapter::failing());

$this->followingRedirects()
    ->from('/notes/'.$note->id)
    ->post('/notes/'.$note->id.'/summarize')
    ->assertOk()
    ->assertSee("Le service d'IA n'a pas repondu.");
```

On simule la panne sans connaître d'URL ni de format de réponse : on remplace
le fournisseur dans le conteneur. Sur la branche de départ, ce test ne peut
pas être écrit, il n'y a ni contrat à remplacer, ni comportement propre à
affirmer.

De même, `SummarizeNoteTest` vérifie sans réseau que la consigne part en rôle
`system` et la note en rôle `user`. Le format JSON de chaque fournisseur n'est
vérifié qu'à un endroit : dans les tests de son adaptateur
(`HttpLlmAdapterTest`, `OllamaAdapterTest`).

## Encadré : quand ne pas poser de frontière

Il n'y a volontairement **aucun** `NoteRepository` dans ce corrigé : les cas
d'usage travaillent directement avec le modèle Eloquent `Note`.

L'IA justifie un port : service externe, lent, faillible, facturé,
remplaçable, dont le fournisseur peut changer pour des raisons qui n'ont rien
de technique. Eloquent, non : c'est un choix structurant du framework, stable
sur la durée du projet, et déjà testable avec SQLite en mémoire. Un port de
plus coûterait des mappers et des entités en double, pour un bénéfice nul ici.

Le critère n'est pas « un port partout », mais « un port là où l'instabilité
et le coût de test le justifient ». C'est le même critère que la grille
applique ensuite aux SDK.

## Encadré : ce que les tests gelés nous ont imposé

`NoteAiTest` affirme :

```php
$this->assertSame('courses,maison,alimentation', $note->fresh()->tags);
```

Le format CSV fait donc partie du contrat observable. Un cast qui renverrait
un tableau casserait ce test, et les tests étaient la règle du jeu. Le cast
`AsTagList` normalise à l'écriture (chaîne ou tableau, minuscules, sans `#`,
sans doublon) et garde la chaîne à la lecture ; la liste passe par l'accessor
`tag_list`. Le découpage n'est plus fait à trois endroits, mais à un seul.

> Une suite de tests ne fige pas seulement le comportement, elle fige aussi une
> partie de la représentation. C'est le prix du filet de sécurité, et il reste
> bon marché à côté d'une réorganisation à l'aveugle.

## Les autres défauts corrigés

| N° | Défaut du code de départ | Corrigé par |
|----|---|---|
| 1  | Contrôleur fourre-tout, méthodes de 60 lignes | `SummarizeNote`, `SuggestTags`, adaptateurs |
| 2  | Validation écrite à la main | `StoreNoteRequest`, `UpdateNoteRequest` |
| 3  | `protected $guarded = []` | `$fillable` explicite sur `Note` |
| 4  | `env()` hors de `config/` | `config/llm.php` |
| 5  | Bloc HTTP et prompt dupliqués | un port, trois adaptateurs, prompts dans `resources/prompts/` |
| 6  | `if ($provider == 'http')` dans le contrôleur | `LlmServiceProvider` piloté par la configuration |
| 7  | Aucun timeout, aucune gestion d'erreur | `timeout()` et `LlmCallFailed` |
| 8  | Lecture du JSON sans garde | vérification de la forme, exception du port |
| 9  | Note concaténée dans la consigne | `Message::system()` et `Message::user()` |
| 10 | Modèle en alias `-latest`, écrit en dur | identifiant daté dans `config/llm.php` |
| 11 | Logique métier dans les vues (`@php`) | accessors `excerpt`, `word_count`, `reading_time` |
| 12 | Tags CSV découpés à trois endroits | cast `AsTagList` et accessor `tag_list` |
| 13 | `Note::all()` puis filtrage en PHP | scopes `matchingTitle` et `tagged`, filtrage en SQL |
| 14 | Pas de route model binding, `first()` sans garde | `Note $note` en signature, 404 automatique |
| 15 | Closures de route qui font du métier, suppression en `GET` | `NoteController::tagged()` et `destroy()`, verbe `DELETE` |
| 16 | URL en dur | `route('notes.show', $note)` partout |
| 17 | `Log::info($request->all())` | supprimé |
| 18 | Code mort commenté, nombres magiques | supprimé ; constantes `WORDS_PER_MINUTE`, `EXCERPT_LENGTH`, `MAX_TAGS` |

Sur la suppression (n° 15) : la route la plus destructrice du projet était la
seule sans test, et répondait à une requête `GET`. Un simple préchargement de
lien par le navigateur pouvait effacer une note. Elle passe en `DELETE`, et
`tests/Feature/NoteDeleteTest.php` la couvre désormais.

## Vérifier soi-même

```bash
./vendor/bin/sail artisan test
```

49 tests, dont les 10 de départ inchangés.

```bash
git diff fyc-tp-s1..fyc-tp-s1-correction -- \
  tests/Feature/NoteCrudTest.php tests/Feature/NoteAiTest.php database/migrations/
```

Ne doit rien afficher : ni les tests fournis, ni la migration n'ont été
touchés.

```bash
grep -rn "api.mistral.ai\|11434\|mistral-small\|llama3.2" app/ routes/ resources/
```

Ne doit rien afficher non plus : plus aucune URL ni aucun nom de modèle en
dehors de `config/`.
