<?php

declare(strict_types=1);

namespace App\Infrastructure\Embeddings;

use App\Domain\Ports\EmbeddingPort;
use App\Domain\Ports\LlmCallFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Vectorisation par un modèle local d'Ollama, via `/api/embed`.
 *
 * L'API accepte une liste de textes dans `input` et renvoie un vecteur par
 * texte dans `embeddings`, dans le même ordre.
 */
final class OllamaEmbeddingAdapter implements EmbeddingPort
{
    public function __construct(
        private readonly string $host,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function embed(array $texts): array
    {
        try {
            $reponse = Http::timeout($this->timeout)
                ->post(rtrim($this->host, '/').'/api/embed', ['model' => $this->model, 'input' => $texts]);
        } catch (ConnectionException $e) {
            throw LlmCallFailed::unreachable('ollama', $e);
        }

        if ($reponse->failed()) {
            throw LlmCallFailed::rejected('ollama', $reponse->status());
        }

        return Vectors::check($reponse->json('embeddings'), count($texts), 'ollama');
    }

    public function modelName(): string
    {
        return 'ollama:'.$this->model;
    }
}
