<?php

/*
 * Configuration du fournisseur de modèle de langage.
 *
 * Seul fichier du projet où apparaissent une URL, une clé ou un nom de
 * modèle. Seul fichier, avec les autres fichiers de config/, où l'on appelle
 * env() : une fois `php artisan config:cache` lancé, Laravel ne lit plus le
 * .env, et un env() placé ailleurs renvoie null.
 */

return [

    /*
     * fake   : doublure de test, aucune requête réseau (valeur par défaut)
     * ollama : modèle local, aucune clé
     * http   : fournisseur en ligne compatible OpenAI (Mistral par défaut)
     */
    'provider' => env('LLM_PROVIDER', 'fake'),

    'http' => [
        'base_url' => env('LLM_HTTP_BASE_URL', 'https://api.mistral.ai/v1'),
        'api_key' => env('LLM_HTTP_API_KEY'),
        // Identifiant daté, pas un alias « -latest » : un alias change de
        // modèle sans prévenir, et c'est un déploiement qu'on ne contrôle pas.
        'model' => env('LLM_HTTP_MODEL', 'mistral-small-2603'),
        'timeout' => (int) env('LLM_HTTP_TIMEOUT', 30),
    ],

    'ollama' => [
        'host' => env('LLM_OLLAMA_HOST', 'http://host.docker.internal:11434'),
        'model' => env('LLM_OLLAMA_MODEL', 'llama3.2:3b'),
        // Un modèle local se charge en mémoire à la première requête : il faut
        // être plus patient qu'avec une API en ligne.
        'timeout' => (int) env('LLM_OLLAMA_TIMEOUT', 120),
        // Vide : on garde la valeur du serveur (OLLAMA_CONTEXT_LENGTH, réglée au
        // chapitre 00). Renseigné : envoyé dans l'option num_ctx de chaque appel.
        'context_length' => env('LLM_OLLAMA_CONTEXT_LENGTH') ? (int) env('LLM_OLLAMA_CONTEXT_LENGTH') : null,
    ],

    /*
     * Version servie de chaque prompt (fichiers resources/prompts/<nom>/<version>.md).
     * Passer à v2 se fait ici, et revenir en arrière aussi.
     */
    'prompts' => [
        'summarize-note' => env('LLM_PROMPT_SUMMARIZE_NOTE', 'v1'),
        'suggest-tags' => env('LLM_PROMPT_SUGGEST_TAGS', 'v1'),
        'note-agent' => env('LLM_PROMPT_NOTE_AGENT', 'v1'),
    ],

];
