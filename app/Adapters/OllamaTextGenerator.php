<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Ports\TextGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Seul endroit du projet qui connait l'API d'Ollama.
 *
 * Le corps de requete n'a rien a voir avec celui de Mistral. C'est justement
 * ce que le port cache au reste de l'application.
 */
final class OllamaTextGenerator implements TextGenerator
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function generate(string $instruction, string $input): string
    {
        try {
            $reponse = Http::timeout($this->timeout)
                ->post(rtrim($this->baseUrl, '/').'/api/generate', [
                    'model' => $this->model,
                    // Ollama distingue nativement la consigne du contenu.
                    'system' => $instruction,
                    'prompt' => $input,
                    'stream' => false,
                ]);
        } catch (ConnectionException $e) {
            throw TextGenerationFailed::unreachable('ollama', $e);
        }

        if ($reponse->failed()) {
            throw TextGenerationFailed::rejected('ollama', $reponse->status());
        }

        $texte = data_get($reponse->json(), 'response');

        if (! is_string($texte) || trim($texte) === '') {
            throw TextGenerationFailed::unexpectedPayload('ollama');
        }

        return trim($texte);
    }
}
