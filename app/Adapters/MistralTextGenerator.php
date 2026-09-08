<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Ports\TextGenerator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Seul endroit du projet qui connait l'API de Mistral.
 */
final class MistralTextGenerator implements TextGenerator
{
    public function __construct(
        private readonly string $url,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function generate(string $instruction, string $input): string
    {
        try {
            $reponse = Http::withToken($this->apiKey)
                ->timeout($this->timeout)
                ->post($this->url, [
                    'model' => $this->model,
                    'messages' => [
                        // L'instruction et le contenu utilisateur restent deux
                        // messages distincts : le second n'est jamais
                        // concatene au premier.
                        ['role' => 'system', 'content' => $instruction],
                        ['role' => 'user', 'content' => $input],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw TextGenerationFailed::unreachable('mistral', $e);
        }

        if ($reponse->failed()) {
            throw TextGenerationFailed::rejected('mistral', $reponse->status());
        }

        $texte = data_get($reponse->json(), 'choices.0.message.content');

        if (! is_string($texte) || trim($texte) === '') {
            throw TextGenerationFailed::unexpectedPayload('mistral');
        }

        return trim($texte);
    }
}
