<?php

return [

    /*
     * Fournisseur utilise par l'application.
     *
     * C'est la seule valeur a changer pour basculer de Mistral a Ollama :
     * aucune ligne de code n'est concernee.
     */
    'provider' => env('AI_PROVIDER', 'ollama'),

    'mistral' => [
        'url' => env('MISTRAL_URL', 'https://api.mistral.ai/v1/chat/completions'),
        'key' => env('MISTRAL_API_KEY'),
        'model' => env('MISTRAL_MODEL', 'mistral-small-latest'),
        'timeout' => (int) env('AI_TIMEOUT', 30),
    ],

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://host.docker.internal:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
        // Un modele local se charge en memoire a la premiere requete : il faut
        // etre plus patient qu'avec une API cloud.
        'timeout' => (int) env('AI_TIMEOUT', 120),
    ],

];
