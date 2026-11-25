<?php

declare(strict_types=1);

namespace App\Infrastructure\Embeddings;

use App\Domain\Ports\EmbeddingPort;
use App\Domain\Ports\LlmCallFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Vectorisation par un fournisseur en ligne au format OpenAI (`/embeddings`) :
 * Mistral, OpenAI, et les autres compatibles.
 *
 * La réponse range les vecteurs dans `data[].embedding`, chacun avec son
 * `index` : on les remet dans l'ordre des textes envoyés plutôt que de
 * supposer que le fournisseur l'a fait.
 */
final class HttpEmbeddingAdapter implements EmbeddingPort
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function embed(array $texts): array
    {
        try {
            $reponse = Http::timeout($this->timeout)
                ->withToken((string) $this->apiKey)
                ->post(rtrim($this->baseUrl, '/').'/embeddings', ['model' => $this->model, 'input' => $texts]);
        } catch (ConnectionException $e) {
            throw LlmCallFailed::unreachable('http', $e);
        }

        if ($reponse->failed()) {
            throw LlmCallFailed::rejected('http', $reponse->status());
        }

        $donnees = $reponse->json('data');

        if (! is_array($donnees)) {
            throw LlmCallFailed::unexpectedPayload('http');
        }

        usort($donnees, fn ($a, $b): int => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return Vectors::check(array_column($donnees, 'embedding'), count($texts), 'http');
    }

    public function modelName(): string
    {
        return 'http:'.$this->model;
    }
}
