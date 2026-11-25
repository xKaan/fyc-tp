<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Adaptateur pour un fournisseur en ligne qui expose l'API « chat
 * completions » au format OpenAI : Mistral, OpenAI, et bien d'autres.
 *
 * Changer de fournisseur compatible, c'est changer l'URL de base, la clé et
 * le modèle dans la configuration : cette classe ne bouge pas.
 */
final class HttpLlmAdapter implements LlmPort
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function complete(array $messages): string
    {
        try {
            $reponse = Http::timeout($this->timeout)
                ->when($this->apiKey, fn (PendingRequest $requete) => $requete->withToken((string) $this->apiKey))
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => array_map(
                        fn (Message $message): array => ['role' => $message->role->value, 'content' => $message->content],
                        $messages,
                    ),
                ]);
        } catch (ConnectionException $e) {
            throw LlmCallFailed::unreachable('http', $e);
        }

        if ($reponse->failed()) {
            throw LlmCallFailed::rejected('http', $reponse->status());
        }

        $texte = $reponse->json('choices.0.message.content');

        if (! is_string($texte) || trim($texte) === '') {
            throw LlmCallFailed::unexpectedPayload('http');
        }

        return trim($texte);
    }
}
