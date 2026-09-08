# TP S1.4 — Corrige

Ce document accompagne la branche `fyc-tp-s1-correction`. Le diff complet de la
seance tient dans :

```bash
git diff fyc-tp-s1..fyc-tp-s1-correction
```

## Ce que l'on cherchait a obtenir

Trois acquis, dans cet ordre :

1. Un appel a un service externe volatil se cache derriere un contrat etroit.
2. Le choix d'implementation est une decision de configuration, pas de code.
3. Une frontiere bien posee rend testable ce qui ne l'etait pas.

Et un quatrieme, plus fin, qui n'apparait qu'ici : savoir ou **ne pas** poser
de frontiere.

## Le diagnostic

`summarize()` et `suggestTags()` faisaient chacune une soixantaine de lignes, et
partageaient le meme squelette copie-colle : lecture de la cle d'API, `if` sur
le fournisseur, `Http::post()`, extraction non gardee de la reponse,
persistance, redirection. Deux fois le meme bloc, a un prompt pres.

Les trois questions de l'enonce :

**Combien d'endroits faut-il modifier pour changer de fournisseur ?**
Deux methodes, quatre branches `if/else`. Et comme c'est du code source, il faut
redeployer. Une decision d'exploitation coutait une livraison.

**Que se passe-t-il si l'API repond une erreur, ou un JSON inattendu ?**

```php
$resume = $reponse->json()['choices'][0]['message']['content'];
```

Sur un corps d'erreur, cette ligne leve une erreur PHP. L'utilisateur voit une
page 500. Il n'y avait aucun `timeout()` non plus : une API lente bloquait la
requete jusqu'a l'expiration du serveur web.

**Comment tester la construction du prompt sans appel reseau ?**
On ne peut pas. Le prompt est construit a l'interieur meme de la methode qui
fait l'appel. On ne peut tester que l'ensemble, avec `Http::fake()`, donc en
connaissant l'URL et le format de reponse du fournisseur — c'est-a-dire en
faisant entrer le fournisseur dans le test.

## Le port

```php
interface TextGenerator
{
    /** @throws TextGenerationFailed */
    public function generate(string $instruction, string $input): string;
}
```

Une methode, deux `string`, un `string`. Lisez surtout ce qui **n'y est pas** :
pas de modele, pas de temperature, pas de cle d'API, pas de nom de fournisseur,
pas de `Response`. Ce sont des details d'implementation.

> Un port decrit ce dont l'application a besoin, pas ce que le fournisseur sait
> faire.

C'est ce qui le rend stable : le jour ou l'on passe a un troisieme fournisseur,
la signature ne bouge pas.

La separation `instruction` / `input` regle au passage l'injection de prompt. Le
contenu de la note n'est plus concatene dans la phrase de consigne — il part
dans un canal distinct, que chaque adapter traduit dans le vocabulaire de son
API.

## Les adapters

`MistralTextGenerator` envoie un tableau `messages` avec des roles. Ollama, lui,
attend deux champs `system` et `prompt` a plat, plus `stream: false`. Les deux
corps de requete n'ont **rien** en commun.

C'est precisement ce que le port cache. Le code metier n'a jamais eu a
connaitre ni l'un ni l'autre.

Chaque adapter ajoute trois gardes que le code de depart n'avait pas :

```php
->timeout($this->timeout)                       // 1. on n'attend pas indefiniment

if ($reponse->failed()) {                        // 2. une erreur HTTP est une panne
    throw TextGenerationFailed::rejected('mistral', $reponse->status());
}

$texte = data_get($reponse->json(), 'choices.0.message.content');
if (! is_string($texte) || trim($texte) === '') {  // 3. la forme est verifiee
    throw TextGenerationFailed::unexpectedPayload('mistral');
}
```

Une seule exception, `TextGenerationFailed`, pour les trois cas. Le code metier
n'a pas a distinguer un timeout d'un quota depasse : il n'en ferait rien de
different, et une exception par cause de panne ferait fuir les details du
fournisseur dans le controleur.

## Les use-cases

```php
final class SummarizeNote
{
    private const INSTRUCTION = 'Resume la note suivante en 3 phrases maximum, en francais. '
        .'Ne reponds que le resume, sans introduction.';

    public function __construct(private readonly TextGenerator $generator) {}

    public function __invoke(Note $note): void
    {
        $note->summary = $this->generator->generate(
            self::INSTRUCTION,
            $note->title."\n".$note->content,
        );

        $note->save();
    }
}
```

Dix lignes utiles. Pas une seule de HTTP. Pas un nom de fournisseur. La seule
dependance a Laravel est Eloquent, et c'est un choix assume (voir plus bas).

Le plafond de cinq tags vit dans `SuggestTags`, pas dans le cast : « au maximum
cinq tags suggeres » est une regle de cette fonctionnalite, pas du format de
stockage.

## La configuration

```php
// config/ai.php
'provider' => env('AI_PROVIDER', 'ollama'),
```

`AiServiceProvider` lit cette valeur et lie le port a l'adapter correspondant.
C'est le seul `match` du projet sur le nom d'un fournisseur, et il est dans un
service provider — c'est-a-dire dans la couche dont c'est le role.

> Changer de fournisseur coute desormais une ligne de `.env` et zero ligne de
> code.

**Pourquoi `env()` ne doit jamais etre appele hors de `config/`.** En production
on execute `php artisan config:cache` : Laravel serialise tous les fichiers de
`config/` dans un seul fichier compile, et **cesse de lire le `.env`**. Tout
appel a `env()` situe ailleurs renvoie alors `null`. Le code de depart aurait
donc bascule silencieusement sur sa valeur par defaut en production. C'est un
bug qui ne se manifeste jamais en local.

## Le test que l'ancienne architecture interdisait

```php
Http::fake(['*' => Http::response('service indisponible', 500)]);

$this->followingRedirects()
    ->from('/notes/'.$note->id)
    ->post('/notes/'.$note->id.'/summarize')
    ->assertOk()
    ->assertSee("Le service d'IA n'a pas repondu.");
```

Sur la branche de depart, ce test ne peut pas etre vert : l'application repond
une 500. Mais surtout, il ne peut pas etre **ecrit** : il n'y avait aucun
comportement propre a affirmer.

La frontiere n'a pas seulement rendu le code testable. Elle a rendu le
comportement *definissable* : il existe maintenant un endroit — le controleur —
dont c'est le role de decider ce que voit l'utilisateur quand l'IA tombe.

## Encadre — quand ne pas poser de frontiere

Il n'y a volontairement **aucun** `NoteRepositoryInterface` dans ce corrige.

L'IA justifie un port : service externe, lent, faillible, facture, remplacable,
et dont le fournisseur peut changer pour des raisons qui n'ont rien a voir avec
la technique.

Eloquent, non : c'est un choix structurant du framework, stable sur la duree de
vie du projet, et deja testable via SQLite en memoire. Un port couterait des
mappers, des entites dupliquees et la perte des fonctionnalites du framework,
pour un benefice nul.

Le critere n'est pas « toujours un port » mais « un port la ou la volatilite et
le cout de test le justifient ». C'est le meme critere que la grille applique
ensuite aux SDK.

## Encadre — ce que les tests geles nous ont impose

`NoteAiTest` affirme :

```php
$this->assertSame('courses,maison,alimentation', $note->fresh()->tags);
```

Le format CSV fait donc partie du contrat observable. Un cast qui renverrait un
tableau, comme on le ferait spontanement, casse ce test — et les tests etaient
la regle du jeu.

Le cast `AsTagList` normalise donc a l'ecriture (il accepte une chaine ou un
tableau, met en minuscules, retire les `#`, dedoublonne) et conserve la chaine a
la lecture. La lecture structuree passe par un accessor :

```php
protected function tagList(): Attribute
{
    return Attribute::get(fn (): array => AsTagList::parse($this->tags));
}
```

Les vues font desormais `@foreach ($note->tag_list as $tag)` au lieu de
redecouper la chaine a la main. Le parsing n'est plus a trois endroits : il est
a un seul, dans la couche modele.

> Une suite de tests ne fige pas seulement le comportement, elle fige aussi une
> partie de la representation. C'est le prix du filet de securite, et il est bon
> marche compare au refactor a l'aveugle.

## Les autres defauts corriges

| #  | Erreur du code de depart | Corrigee par |
|----|---|---|
| 1  | Controleur god-object, methodes de 70 lignes | `SummarizeNote`, `SuggestTags`, adapters |
| 2  | Validation inline sur `$request->all()` | `StoreNoteRequest`, `UpdateNoteRequest` |
| 3  | `protected $guarded = []` | `$fillable` explicite sur `Note` |
| 4  | `env()` hors de `config/` | `config/ai.php` |
| 5  | Bloc HTTP et prompt dupliques | un port, deux adapters |
| 6  | `if ($provider == 'mistral')` dans le metier | `AiServiceProvider` pilote par la config |
| 7  | Aucun timeout, aucune gestion d'erreur | `timeout()` + `TextGenerationFailed` |
| 8  | Acces tableau non garde sur le JSON | garde `is_string()` + exception typee |
| 9  | Contenu utilisateur concatene au prompt | signature `generate($instruction, $input)` |
| 10 | Logique metier dans les vues (`@php`) | accessors `excerpt`, `word_count`, `reading_time` |
| 11 | Tags CSV parses a trois endroits | cast `AsTagList` + accessor `tag_list` |
| 12 | `Note::all()` puis filtrage en PHP | scopes `matchingTitle` et `tagged`, filtrage en SQL |
| 13 | Pas de route model binding, `first()` sans garde | `Note $note` en signature, 404 automatique |
| 14 | Closures de route faisant du metier, suppression en `GET` | `NoteController::tagged()` et `destroy()`, verbe `DELETE` |
| 15 | URLs en dur | `route('notes.show', $note)` partout |
| 16 | `Log::info($request->all())` | supprime |
| 17 | Code mort commente, nombres magiques | supprime ; constantes `WORDS_PER_MINUTE`, `EXCERPT_LENGTH`, `MAX_TAGS` |
| 18 | Nommage mele francais / anglais | identifiants en anglais, commentaires en francais |

Sur la suppression (n°14) : la route la plus destructrice du projet etait la
seule sans test, et repondait a une requete `GET`. N'importe quel prefetch de
navigateur pouvait effacer une note. Elle passe en `DELETE`, et
`tests/Feature/NoteDeleteTest.php` la couvre desormais.

## Verifier soi-meme

```bash
./vendor/bin/sail artisan test
```

42 tests, dont les 10 de depart inchanges.

```bash
git diff fyc-tp-s1..fyc-tp-s1-correction -- \
  tests/Feature/NoteCrudTest.php tests/Feature/NoteAiTest.php database/migrations/
```

Ne doit rien afficher : ni les tests fournis, ni la migration n'ont ete touches.

```bash
grep -rn "api.mistral.ai\|11434\|mistral-small\|llama3.2" app/ routes/ resources/
```

Ne doit rien afficher non plus : plus une seule URL ni un seul nom de modele en
dehors de `config/`.
