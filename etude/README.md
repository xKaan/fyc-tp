# Étude de cas S1.2 : un appel d'IA dans un contrôleur, timeout réel

Ce dossier accompagne la partie 2.2 du chapitre 01 (« Pourquoi l'IA complique
les architectures classiques »). Il fait tourner l'application Mes Notes,
**sans rien changer à son contrôleur**, derrière une pile qui ressemble à la
production :

| Service | Rôle | Réglage qui compte |
|---------|------|--------------------|
| `nginx` | Serveur web devant PHP | Attend PHP `NGINX_DELAI` secondes (60 par défaut), puis renvoie une erreur 504 |
| `php` | PHP-FPM | 8 processus, `max_execution_time = 30` |
| `faux-llm` | Faux fournisseur d'IA, au format de l'API de Mistral | Répond après `LLM_DELAI` secondes (8 par défaut) et journalise chaque appel reçu |

Le faux fournisseur évite d'avoir besoin d'une clé et de payer des appels :
on règle sa lenteur à volonté. Chaque appel qu'il reçoit serait facturé par
un vrai fournisseur, que l'application attende la réponse ou non.

Le seul changement par rapport à la branche `fyc-tp-s1` : l'adresse du
fournisseur se lit dans `LLM_HTTP_URL` (avec l'adresse de Mistral par défaut),
pour pouvoir pointer vers le faux fournisseur.

## Démarrer

Depuis la racine du dépôt, après l'installation décrite dans le
[README principal](../README.md) (dépendances installées, `.env` créé) :

```bash
docker compose -f etude/compose.yaml up -d --build
docker compose -f etude/compose.yaml exec php php artisan migrate --force
```

L'application répond sur http://localhost:8080. Dans un second terminal,
gardez le journal du faux fournisseur sous les yeux :

```bash
docker compose -f etude/compose.yaml logs -f faux-llm
```

Le script `etude/charge.sh` crée une note, envoie N clics simultanés sur
« Résumer », puis chronomètre l'ouverture de la page d'accueil pendant que les
appels sont en cours. Il termine par l'état de PHP-FPM depuis son démarrage.

## Scénario 1 : lundi, 9 h, les processus sont tous occupés

Le fournisseur répond en 8 secondes. Dix utilisateurs cliquent en même temps.

```bash
etude/charge.sh 10
```

Ce qu'on observe :

- Huit clics reviennent après 8 secondes, les deux autres après 16 : ils ont
  attendu qu'un processus se libère.
- La page d'accueil, qui ne fait aucun appel à l'IA et répond d'habitude en
  quelques millisecondes, attend plusieurs secondes.
- `max active processes: 8` et `max listen queue` supérieur à zéro : tous les
  processus étaient pris, des requêtes ont fait la queue.

## Scénario 2 : lundi, 9 h 01, le fournisseur ralentit

Le fournisseur met maintenant 90 secondes. Avant de lancer, notez vos
hypothèses : qui coupe, et au bout de combien de temps ? `max_execution_time`
(30 s) ? nginx (60 s) ? Personne ?

```bash
LLM_DELAI=90 docker compose -f etude/compose.yaml up -d faux-llm
etude/charge.sh 1
```

Ce qu'on observe :

- Le clic échoue avec une erreur 500 après **30 secondes**.
- Le journal de PHP montre `cURL error 28: Operation timed out after 30001
  milliseconds` :

  ```bash
  docker compose -f etude/compose.yaml logs php | grep "cURL error"
  ```

- Le journal du faux fournisseur montre que l'appel a bien été reçu, donc
  facturé, puis que sa réponse est perdue quand elle arrive.

Ce n'est pas `max_execution_time` qui a coupé : sous Linux, il ne compte que
le temps passé à exécuter le script, pas l'attente du réseau. C'est le client
HTTP de Laravel, qui applique **par défaut** un délai de 30 secondes à toute
requête. Personne ne l'a choisi : le contrôleur ne fixe aucun délai, il hérite
de celui du framework, qui n'a rien à voir avec le temps qu'un utilisateur
accepte d'attendre.

## Scénario 3 : un proxy plus pressé que PHP

En production, un proxy ou un répartiteur de charge se trouve souvent devant
le serveur web, avec son propre délai. Raccourcissons celui de nginx à
20 secondes, le fournisseur restant à 90 :

```bash
NGINX_DELAI=20 docker compose -f etude/compose.yaml up -d nginx
etude/charge.sh 1 &
sleep 24 && curl -s http://localhost:8080/fpm-status | grep "^active processes"
```

Ce qu'on observe :

- L'utilisateur reçoit une erreur 504 après 20 secondes.
- Quatre secondes plus tard, deux processus sont encore actifs : celui qui
  répond à la page d'état, et celui du clic, **qui attend toujours le
  fournisseur** alors que l'utilisateur est parti. Il ne se libère qu'à
  30 secondes, quand le client HTTP abandonne à son tour.

Un utilisateur qui reclique après son erreur 504 relance un appel facturé et
occupe un processus de plus, alors que le premier n'est pas encore libéré.

## Ce qu'il faut en retenir

Trois délais coexistent, posés par trois couches différentes, et aucun n'a
été choisi pour cet appel :

| Couche | Délai | Ce qui se passe quand il expire |
|--------|-------|---------------------------------|
| PHP (`max_execution_time`) | 30 s de calcul | Ne se déclenche pas : l'attente réseau ne compte pas |
| Client HTTP de Laravel | 30 s par défaut | Exception, erreur 500, le processus se libère |
| Serveur web ou proxy | 60 s chez nginx, variable ailleurs | Erreur 504 pour l'utilisateur, le processus PHP continue d'attendre |

Dans tous les cas, l'appel est parti chez le fournisseur et sera facturé. Les
parades (délai explicite sur l'appel, traitement en arrière-plan, file
d'attente) sont construites en séance 3.

## Arrêter

```bash
docker compose -f etude/compose.yaml down
```
