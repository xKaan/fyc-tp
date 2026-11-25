# Mes Notes : application de prise de notes avec IA

Petite application Laravel de prise de notes, avec deux fonctionnalités d'IA :
résumer une note et suggérer des tags. Aucun SDK d'IA n'est utilisé : tout
passe par des appels HTTP directs.

Ce dépôt sert de support au TP S1.3 du cours (« un port, trois
adaptateurs »). Voir [TP-S1-enonce.md](TP-S1-enonce.md).

- Branche `fyc-tp-s1` : le code de départ, à réorganiser.
- Branche `fyc-tp-s1-correction` : le corrigé, avec `TP-S1-corrige.md`.

## Prérequis

L'environnement du chapitre 00 doit être installé : Docker Desktop (avec
l'intégration WSL activée sous Windows, Settings → Resources → WSL
Integration) et Ollama avec un petit modèle. Aucune installation de PHP ni de
Composer n'est nécessaire.

## Installation

```bash
git clone https://github.com/xKaan/fyc-tp.git
cd fyc-tp
cp .env.example .env
```

Installez les dépendances sans avoir PHP en local :

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp \
  -v "$PWD":/var/www/html -w /var/www/html \
  laravelsail/php84-composer:latest \
  bash -c "composer install && php artisan key:generate && php artisan migrate"
```

Puis démarrez l'application :

```bash
./vendor/bin/sail up -d
```

Elle répond sur http://localhost. Pour l'arrêter : `./vendor/bin/sail down`.

## Commandes utiles

```bash
./vendor/bin/sail artisan test          # lance les tests
./vendor/bin/sail artisan migrate       # applique les migrations
./vendor/bin/sail artisan tinker        # console interactive
```

Les tests n'appellent jamais de service d'IA réel : les requêtes HTTP sont
simulées. Ils fonctionnent donc sans clé et sans connexion.

## Configurer l'IA

Le fournisseur se choisit dans `.env`, par la variable `LLM_PROVIDER` :

| Valeur | Fournisseur | Ce qu'il faut |
|---|---|---|
| `ollama` | modèle local, valeur conseillée | Ollama lancé, modèle téléchargé |
| `http` | fournisseur en ligne compatible OpenAI (Mistral) | une clé dans `LLM_HTTP_API_KEY` |
| `fake` | doublure de test, aucune requête réseau | rien (disponible une fois le TP fait) |

**Ollama est le choix conseillé** : aucune clé à obtenir, aucun quota à
partager, et l'application fonctionne sans connexion internet.

### Ollama (local)

Le modèle utilisé est `llama3.2:3b`. S'il n'est pas déjà installé :

```bash
ollama pull llama3.2:3b
```

L'application tourne dans un conteneur, Ollama sur votre machine : le
conteneur le joint sur `host.docker.internal:11434`, l'adresse déjà utilisée
par l'application. Vérifiez qu'Ollama répond :

```bash
curl http://localhost:11434/api/tags
```

### Fournisseur en ligne (Mistral)

Créez une clé sur https://console.mistral.ai, puis dans `.env` :

```dotenv
LLM_PROVIDER=http
LLM_HTTP_API_KEY=votre-cle
```

Le palier gratuit suffit pour ce TP.

### Le premier appel à Ollama est lent

Ollama ne charge le modèle en mémoire qu'à la première requête. Ce chargement
prend plusieurs dizaines de secondes, parfois plus que le délai d'attente de
l'application, qui renvoie alors une erreur. Les appels suivants sont bien plus
rapides.

**Chauffez donc le modèle avant de commencer**, une fois par session :

```bash
curl http://localhost:11434/api/chat \
  -d '{"model":"llama3.2:3b","messages":[{"role":"user","content":"bonjour"}],"stream":false}'
```

Attendez que la commande rende la main. Le modèle est déchargé de la mémoire
après quelques minutes d'inactivité : après une longue pause, rechauffez-le.
