# Grille comparative : brancher une IA avec ou sans SDK

Version remplie (corrigé). La colonne « Mon code » donne une réponse type :
la vôtre peut être formulée autrement. État des bibliothèques au 24/11/2026 :
`laravel/ai` v1.4.2, Spring AI 2.1.0.

| Question | Mon code | Laravel AI SDK | Spring AI | Ce qui reste vrai partout |
|---|---|---|---|---|
| **1. Forme du contrat.** Que voit le code métier quand il veut une réponse du modèle ? | Une interface `LlmPort`, une méthode `complete()`, une liste de `Message` avec un rôle, un texte en retour. Le vocabulaire est celui de l'application. | Une classe d'agent par besoin (contrat `Agent` et trait `Promptable`), avec ses instructions, et le fournisseur et le modèle en attributs (`#[Provider]`, `#[Model]`). Contrat riche : outils, sortie structurée, conversation. | `ChatClient` en API fluide (`.system()`, `.user()`, `.call()`), au-dessus de `ChatModel`. Contrat large, pensé pour couvrir tous les fournisseurs. | Une consigne et un contenu utilisateur séparés par leur rôle. Le code métier ne voit ni URL, ni clé, ni JSON du fournisseur. |
| **2. Changer de fournisseur.** Combien de fichiers faut-il modifier pour passer d'Ollama au modèle en ligne ? | Une ligne de `.env` (`LLM_PROVIDER`). Zéro ligne de code. | Une valeur de configuration ou d'attribut, parmi les fournisseurs que le SDK prend en charge. Bascule automatique possible vers un second fournisseur, sur certaines erreurs seulement. | Une dépendance (starter du fournisseur) et des propriétés. Pas de bascule automatique entre modèles : on la code soi-même. | Le choix de l'adaptateur est une décision de configuration, prise à un seul endroit (le « configurateur »). |
| **3. Tester sans réseau.** De quoi avez-vous besoin pour tester le cœur métier sans appeler de modèle ? | `FakeLlmAdapter`, une trentaine de lignes : réponse fixe et messages reçus enregistrés. | La méthode statique `fake()` de chaque agent (`MonAgent::fake()`), avec `assertPrompted` et `preventStrayPrompts()`. | Un mock de `ChatModel` (Mockito, par exemple). L'API `Evaluator` n'est pas une doublure : elle fait juger une réponse par un autre modèle. | Une doublure branchée sur le contrat. On teste le code autour du modèle, pas le modèle. |
| **4. Ce qui reste à écrire.** Réessais, streaming, appels d'outils, comptage de tokens : qu'auriez-vous à écrire vous-même ? | Tout : réessais, streaming, appels d'outils, comptage des tokens. C'est le prix d'un contrat étroit, et le programme des séances 2 et 3. | Outils, sortie structurée, streaming et comptage des tokens sont fournis. Avant de basculer, une seule nouvelle tentative, si `Retry-After` vaut 10 s au plus : ni backoff, ni jitter, ni coupe-circuit. | Outils (`@Tool`), streaming, observations Micrometer fournis. Réessais par défaut (jusqu'à 10 tentatives, délai croissant, sans jitter) ; coupe-circuit avec Resilience4j. | Un SDK évite d'écrire du code, pas de décider : quand réessayer, quand basculer, quoi tracer reste à votre charge. |
| **5. Coût de sortie.** Vous abandonnez cette approche dans deux ans : que faut-il réécrire ? | Faible : on remplace les adaptateurs, le port et les cas d'usage ne bougent pas. | Moyen à élevé si les classes d'agent se sont répandues dans le code métier ; faible si le SDK est resté derrière votre port. | Élevé : en sortir revient souvent à sortir de l'écosystème Spring. | Un SDK placé **derrière** votre port se remplace comme n'importe quel adaptateur. |

## Question de synthèse

*Dans quelle situation choisiriez-vous votre port maison plutôt qu'un SDK ?
Et dans quelle situation feriez-vous l'inverse ?*

Le port maison se justifie quand le besoin est étroit et stable, ici obtenir
une complétion de texte, et quand on veut garder la maîtrise du coût de
sortie. Il coûte peu : une interface, trois adaptateurs, un binding.

Le SDK se justifie dès que le besoin s'élargit : outils, streaming, sortie
structurée, conversation. Réécrire tout cela soi-même revient à réécrire le
SDK, en moins bien testé.

Rien n'interdit les deux : un SDK branché **derrière** le port, comme un
quatrième adaptateur. C'est souvent la position la plus confortable.
