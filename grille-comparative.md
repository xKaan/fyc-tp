# Grille comparative — quatre facons de brancher une IA

A remplir pendant le TP.

- **Avant** : le code que vous avez trouve en arrivant (`NoteController`).
- **Apres** : votre code une fois reorganise autour d'un port.
- **Laravel AI SDK** et **Spring AI** : ce que ces bibliotheques imposent et
  apportent. Documentation fournie par l'enseignant.

Repondez en une ou deux phrases par case. Une case vide est une reponse
acceptable si vous ne savez pas — dites-le a l'oral.

---

## 1. Forme du contrat

*Que voit le code metier quand il veut generer du texte ? Combien de methodes,
combien de parametres, quel vocabulaire ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

## 2. Cout d'un changement de fournisseur

*Combien de fichiers faut-il modifier pour passer de Mistral a Ollama ? Faut-il
redeployer ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

## 3. Testabilite sans reseau

*Comment testez-vous la logique metier sans appeler un service externe ? De
quoi avez-vous besoin pour cela ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

## 4. Couplage au framework

*Si vous deviez reutiliser cette logique metier ailleurs, qu'emporteriez-vous
avec vous ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

## 5. Ce qui est fourni gratuitement

*Streaming, appels d'outils, reessais automatiques, observabilite, gestion des
quotas, comptage de jetons... Qu'auriez-vous a ecrire vous-meme ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

## 6. Ce qui est impose en echange

*Dependances ajoutees, vocabulaire a apprendre, rythme des mises a jour,
version minimale du langage ou du framework.*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

## 7. Cout de sortie

*Vous voulez abandonner cette approche dans deux ans. Que faut-il reecrire ?*

| Avant | Apres | Laravel AI SDK | Spring AI |
|---|---|---|---|
|  |  |  |  |

---

## Question de synthese

Vous demarrez un nouveau projet la semaine prochaine. Dans quelle situation
choisiriez-vous **votre port maison** plutot qu'un SDK ? Et dans quelle
situation feriez-vous l'inverse ?

Ecrivez trois lignes. Il n'y a pas de bonne reponse attendue, mais il y a des
reponses mal argumentees.
