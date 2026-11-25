<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Adaptateur pour un modèle exécuté en local par Ollama, via son API native
 * `/api/chat`.
 *
 * Ollama expose aussi une API compatible OpenAI (`/v1/chat/completions`) :
 * on pourrait le brancher sur HttpLlmAdapter. On garde un adaptateur à part
 * parce que le contrat n'est pas exactement le même : certains paramètres
 * OpenAI ne sont pas pris en charge, et la fenêtre de contexte (4096 tokens
 * par défaut) se règle ici, par l'option `num_ctx`, ou côté serveur avec
 * OLLAMA_CONTEXT_LENGTH. Le port, lui, ne change pas.
 */
final class OllamaAdapter implements LlmPort
{
    public function __construct(
        private readonly string $host,
        private readonly string $model,
        private readonly int $timeout,
        private readonly ?int $contextLength = null,
    ) {}

    public function complete(array $messages): string
    {
        $corps = [
            'model' => $this->model,
            'messages' => array_map(
                fn (Message $message): array => ['role' => $message->role->value, 'content' => $message->content],
                $messages,
            ),
            'stream' => false,
        ];

        if ($this->contextLength !== null) {
            $corps['options'] = ['num_ctx' => $this->contextLength];
        }

        try {
            $reponse = Http::timeout($this->timeout)
                ->post(rtrim($this->host, '/').'/api/chat', $corps);
        } catch (ConnectionException $e) {
            throw LlmCallFailed::unreachable('ollama', $e);
        }

        if ($reponse->failed()) {
            throw LlmCallFailed::rejected('ollama', $reponse->status());
        }

        $texte = $reponse->json('message.content');

        if (! is_string($texte) || trim($texte) === '') {
            throw LlmCallFailed::unexpectedPayload('ollama');
        }

        return trim($texte);
    }
}
