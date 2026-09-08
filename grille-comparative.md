# Grille comparative — quatre facons de brancher une IA

Version remplie (corrige).

- **Avant** : le code trouve en arrivant (`NoteController`).
- **Apres** : le code reorganise autour d'un port.
- **Laravel AI SDK** et **Spring AI** : ce que ces bibliotheques imposent et
  apportent.

---

## 1. Forme du contrat

*Que voit le code metier quand il veut generer du texte ? Combien de methodes,
combien de parametres, quel vocabulaire ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Aucun contrat. Le code metier appelle `Http::post()` et lit `['choices'][0]['message']['content']` : il connait le format de reponse du fournisseur. | Une interface, une methode, deux `string`, un `string`. Le vocabulaire est celui de l'application. | Une facade riche : messages, outils, streaming, sorties structurees. Le vocabulaire est celui du domaine « LLM », pas celui de l'application. | `ChatClient` fluide, `ChatModel`, `Prompt`, `Message`, `Advisor`. Contrat large et abstrait, pense pour couvrir tous les fournisseurs. |

## 2. Cout d'un changement de fournisseur

*Combien de fichiers faut-il modifier pour passer de Mistral a Ollama ? Faut-il
redeployer ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Deux methodes, quatre branches `if/else` a editer, plus un redeploiement. Aucun test ne protege l'operation. | Une ligne de `.env`. Zero ligne de code, zero redeploiement. | Une ligne de configuration egalement — mais uniquement parmi les fournisseurs que le SDK supporte. | Un changement de dependance Maven et de proprietes. Tres large catalogue de fournisseurs. |

## 3. Testabilite sans reseau

*Comment testez-vous la logique metier sans appeler un service externe ? De
quoi avez-vous besoin pour cela ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Uniquement `Http::fake()`, donc en connaissant l'URL et le format de reponse du fournisseur. Le prompt n'est pas testable isolement. | `FakeTextGenerator`, trente lignes, aucun reseau. Le prompt est verifiable directement. | Faux client fourni par le SDK. Efficace, mais on teste contre l'abstraction du SDK, pas contre la sienne. | `ChatModel` mocke, ou un modele de test. Meme remarque. |

## 4. Couplage au framework

*Si vous deviez reutiliser cette logique metier ailleurs, qu'emporteriez-vous
avec vous ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Total : la logique metier vit dans un controleur Laravel et dans une facade Laravel. Rien n'est reutilisable. | Le port est du PHP pur. Les use-cases ne dependent que d'Eloquent. Seuls les adapters connaissent Laravel. | La logique metier depend du vocabulaire du SDK, donc de Laravel et du rythme du SDK. | Fort couplage a Spring : injection, auto-configuration, `application.yml`. |

## 5. Ce qui est fourni gratuitement

*Streaming, appels d'outils, reessais automatiques, observabilite, gestion des
quotas, comptage de jetons... Qu'auriez-vous a ecrire vous-meme ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Rien. | Rien non plus : reessais, streaming, comptage de jetons et observabilite restent a ecrire. C'est le cout assume d'un contrat etroit. | Streaming, appels d'outils, sorties structurees, reessais, journalisation, gestion des quotas. | Idem, plus RAG, memoire de conversation, `Advisor`, integration Micrometer. |

## 6. Ce qui est impose en echange

*Dependances ajoutees, vocabulaire a apprendre, rythme des mises a jour,
version minimale du langage ou du framework.*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Rien, mais c'est ce « rien » qui coute : chaque besoin nouveau se paie en copier-coller. | Le code des deux adapters est a maintenir soi-meme quand une API evolue. | Une dependance de plus, un vocabulaire a apprendre, une version minimale de Laravel et de PHP, et un rythme de mise a jour subi. | Dependances Spring Boot, version minimale de Java, et le modele mental Spring dans son ensemble. |

## 7. Cout de sortie

*Vous voulez abandonner cette approche dans deux ans. Que faut-il reecrire ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
| Eleve : le fournisseur est diffus dans le code metier, il n'y a pas de point de coupe. | Faible : on jette les adapters, le port et les use-cases ne bougent pas. | Moyen a eleve : le vocabulaire du SDK a diffuse dans le code metier ; en sortir demande de reecrire les appels. | Eleve : sortir de Spring AI revient souvent a sortir de Spring. |

---

## Question de synthese

*Vous demarrez un nouveau projet la semaine prochaine. Dans quelle situation
choisiriez-vous votre port maison plutot qu'un SDK ? Et dans quelle situation
feriez-vous l'inverse ?*

Le port maison se justifie quand le besoin est etroit et stable — ici, generer
du texte a partir d'une consigne — et quand on veut garder la maitrise du cout
de sortie. Il coute peu : une interface, deux adapters, un binding.

Le SDK se justifie des que le besoin s'elargit : appels d'outils, streaming,
RAG, memoire de conversation, observabilite. Reecrire tout cela soi-meme
derriere un port etroit revient a reecrire le SDK, en moins bien teste.

La bascule se fait donc sur l'etendue du besoin, pas sur le gout. Et rien
n'interdit de mettre un SDK *derriere* un port : c'est meme la position la plus
confortable, tant que le port reste etroit.
