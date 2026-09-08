# TP S1.4 — Reorganiser un code « fourre-tout »

**Duree : 50 minutes.**

## Le contexte

Vous reprenez une petite application Laravel de prise de notes. Elle marche.
Les tests passent. Un stagiaire l'a ecrite en trois jours, il est parti, et on
vous demande maintenant deux choses :

1. Pouvoir passer de Mistral a Ollama **sans toucher au code**, parce que le
   client ne veut plus envoyer ses notes dans le cloud.
2. Que l'application **n'affiche plus une erreur 500** quand le service d'IA
   ne repond pas.

Ces deux demandes sont aujourd'hui tres couteuses. Le but du TP est de
comprendre pourquoi, et de reorganiser le code pour qu'elles deviennent
triviales.

## Verification de depart

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan test
```

Vous devez voir **10 tests verts**. Si ce n'est pas le cas, arretez-vous et
appelez l'enseignant.

Les tests ne font aucun appel reseau. Pour utiliser reellement les boutons
d'IA dans le navigateur, il faut en plus qu'Ollama tourne et que son modele
soit charge en memoire — voir la section « le premier appel est lent » du
[README](README.md).

## Regles du jeu

1. **Les fichiers `tests/Feature/NoteCrudTest.php` et
   `tests/Feature/NoteAiTest.php` ne doivent pas etre modifies.** Pas une
   ligne. Ils sont votre filet de securite : ils decrivent le comportement
   attendu de l'application, pas son organisation interne.
2. La migration ne doit pas etre modifiee non plus. Un changement
   d'architecture ne touche pas la base de donnees.
3. Vous n'avez le droit a **aucun SDK d'IA**. Uniquement le client HTTP de
   Laravel.
4. A la fin, `./vendor/bin/sail artisan test` doit toujours etre vert.

## Deroule

### Etape 1 — Lire et diagnostiquer (10 min)

Ouvrez `app/Http/Controllers/NoteController.php`.

Lisez les methodes `summarize()` et `suggestTags()`. Listez par ecrit tout ce
qui vous derange. Pour chaque point, repondez a la question : **qu'est-ce que
cela m'empeche de faire ?**

Trois questions pour guider la lecture :

- Combien d'endroits faut-il modifier pour changer de fournisseur d'IA ?
- Que se passe-t-il si l'API repond une erreur, ou un JSON inattendu ?
- Comment testeriez-vous la construction du prompt sans faire d'appel reseau ?

Remplissez la colonne « Avant » de [grille-comparative.md](grille-comparative.md).

### Etape 2 — Poser la frontiere (25 min)

Reorganisez le code autour d'un **port** : une interface qui decrit ce dont
votre application a besoin, en son propre vocabulaire, sans rien dire de la
maniere dont c'est realise.

Objectif a atteindre :

```php
interface TextGenerator
{
    public function generate(string $instruction, string $input): string;
}
```

Puis :

- Une implementation pour Mistral, une pour Ollama. Ce sont les **adapters**.
  C'est le seul endroit du projet ou l'on trouve une URL, une cle d'API, un
  nom de modele ou un `Http::post()`.
- Le code qui orchestre (recuperer la note, construire le prompt, appeler le
  port, enregistrer le resultat) sort du controleur.
- Le choix du fournisseur devient une **decision de configuration** : un
  fichier `config/ai.php`, et un binding dans un service provider.
- Le controleur ne fait plus que du HTTP : recevoir une requete, declencher
  une action, renvoyer une reponse.

Attention a deux pieges :

- `env()` ne doit jamais etre appele ailleurs que dans un fichier de `config/`.
  Cherchez pourquoi : la reponse tient au cache de configuration.
- Le contenu de la note ne doit pas etre concatene dans la phrase
  d'instruction du prompt. Separez les deux.

### Etape 3 — Encaisser la panne (5 min)

Ajoutez un fichier `tests/Feature/NoteAiFailureTest.php` qui simule une API
d'IA repondant une erreur 500, et qui verifie que l'utilisateur voit un
message lisible plutot qu'une page d'erreur.

Ce test etait **impossible a ecrire** sur le code de depart. Prenez le temps de
comprendre pourquoi votre reorganisation le rend possible.

### Etape 4 — Comparer avec les SDK (10 min)

Remplissez les colonnes « Laravel AI SDK » et « Spring AI » de la grille.

La question n'est pas de savoir qui a raison. Elle est de savoir **ce que
chaque approche vous fait gagner, et ce qu'elle vous prend en echange**.

## Ce qui est evalue

- Les 10 tests de depart passent toujours, sans avoir ete modifies.
- Basculer `AI_PROVIDER` entre `mistral` et `ollama` ne demande aucune
  modification de code.
- Aucune URL, cle ou nom de modele en dehors de `config/`.
- Le nouveau test de panne passe.
- La grille est remplie et argumentee.

## Si vous avez fini en avance

Le controleur de depart contient une quinzaine d'autres defauts : validation
ecrite a la main, logique metier dans les vues, requetes chargees en memoire
avant d'etre filtrees en PHP, suppression par une requete GET, donnees
utilisateur envoyees dans les logs... Corrigez-en autant que vous pouvez, en
gardant les tests verts.
