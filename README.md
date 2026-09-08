# Mes Notes — application de prise de notes avec IA

Petite application Laravel de prise de notes, avec deux fonctionnalites d'IA :
resumer une note, et suggerer des tags. Aucun SDK d'IA n'est utilise : tout
passe par des appels HTTP directs.

Deux fournisseurs sont possibles :

- **Mistral** — API cloud, necessite une cle
- **Ollama** — modele execute en local sur votre machine

Ce depot sert de support au TP S1.4 (« reorganiser un code fourre-tout »).
Voir [TP-S1-enonce.md](TP-S1-enonce.md).

## Prerequis

- Docker Desktop, avec l'integration WSL activee si vous etes sous Windows
  (Settings → Resources → WSL Integration)

Aucune installation de PHP ni de Composer n'est necessaire.

## Installation

```bash
git clone <url-du-depot> fyc-tp
cd fyc-tp
cp .env.example .env
```

Installez les dependances sans avoir PHP en local :

```bash
docker run --rm -u "$(id -u):$(id -g)" -e COMPOSER_HOME=/tmp \
  -v "$PWD":/var/www/html -w /var/www/html \
  laravelsail/php84-composer:latest \
  bash -c "composer install && php artisan key:generate && php artisan migrate"
```

Puis demarrez l'application :

```bash
./vendor/bin/sail up -d
```

Elle repond sur http://localhost. Pour l'arreter : `./vendor/bin/sail down`.

## Commandes utiles

```bash
./vendor/bin/sail artisan test          # lance les tests
./vendor/bin/sail artisan migrate       # applique les migrations
./vendor/bin/sail artisan tinker        # console interactive
```

Les tests n'appellent jamais de service d'IA reel : les requetes HTTP sont
simulees. Ils fonctionnent donc sans cle et sans connexion.

## Configurer l'IA

Le fournisseur se choisit dans `.env` :

```dotenv
AI_PROVIDER=ollama
```

**Ollama est le choix conseille pour ce TP** : aucune cle a obtenir, aucun
quota a partager, et l'application fonctionne sans connexion internet.

### Option 1 — Mistral (cloud)

Creez une cle sur https://console.mistral.ai, puis renseignez
`MISTRAL_API_KEY` dans `.env`. Le palier gratuit suffit largement pour ce TP.

### Option 2 — Ollama (local)

Installez Ollama depuis https://ollama.com, puis telechargez un modele :

```bash
ollama pull llama3.2:3b
```

Dans `.env` :

```dotenv
AI_PROVIDER=ollama
```

**Sous Windows avec Docker** : Ollama tourne sur Windows tandis que Laravel
tourne dans un conteneur. Le conteneur joint alors Ollama sur
`host.docker.internal:11434`, ce qui est deja l'adresse utilisee par
l'application.

Verifiez qu'Ollama repond :

```bash
curl http://localhost:11434/api/tags
```

### Important : le premier appel est lent

Ollama ne charge le modele en memoire qu'a la premiere requete. Ce chargement
prend plusieurs dizaines de secondes — parfois plus que le delai d'attente de
l'application, qui renvoie alors une erreur. Les appels suivants sont
immediats : moins d'une seconde sur une machine equipee d'un GPU.

**Chauffez donc le modele avant de commencer**, une seule fois par session :

```bash
curl http://localhost:11434/api/generate \
  -d '{"model":"llama3.2:3b","prompt":"bonjour","stream":false}'
```

Attendez que la commande rende la main. L'application repondra ensuite
normalement. Le modele est decharge de la memoire apres quelques minutes
d'inactivite : si vous reprenez apres une longue pause, rechauffez-le.
