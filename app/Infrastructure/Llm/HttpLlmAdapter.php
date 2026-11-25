<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Domain\Ports\AssistantTurn;
use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;
use App\Domain\Ports\Role;
use App\Domain\Ports\ToolCall;
use App\Domain\Ports\ToolCallingPort;
use App\Domain\Ports\ToolDefinition;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Adaptateur pour un fournisseur en ligne qui expose l'API « chat
 * completions » au format OpenAI : Mistral, OpenAI, et bien d'autres.
 *
 * Changer de fournisseur compatible, c'est changer l'URL de base, la clé et
 * le modèle dans la configuration : cette classe ne bouge pas.
 *
 * Séance 2 : next() ajoute les outils. Au format OpenAI, un appel d'outil
 * porte un identifiant (`id`) que le résultat doit rappeler
 * (`tool_call_id`), et ses arguments arrivent en chaîne JSON.
 */
final class HttpLlmAdapter implements LlmPort, ToolCallingPort
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function complete(array $messages): string
    {
        $reponse = $this->post(['model' => $this->model, 'messages' => $this->wireMessages($messages)]);

        $texte = $reponse->json('choices.0.message.content');

        if (! is_string($texte) || trim($texte) === '') {
            throw LlmCallFailed::unexpectedPayload('http');
        }

        return trim($texte);
    }

    public function next(array $messages, array $tools): AssistantTurn
    {
        $corps = ['model' => $this->model, 'messages' => $this->wireMessages($messages)];

        if ($tools !== []) {
            $corps['tools'] = array_map(fn (ToolDefinition $outil): array => [
                'type' => 'function',
                'function' => [
                    'name' => $outil->name,
                    'description' => $outil->description,
                    'parameters' => $outil->parameters,
                ],
            ], $tools);
        }

        $message = $this->post($corps)->json('choices.0.message');

        if (! is_array($message)) {
            throw LlmCallFailed::unexpectedPayload('http');
        }

        $appels = [];

        foreach ($message['tool_calls'] ?? [] as $appel) {
            $nom = $appel['function']['name'] ?? null;

            if (! is_string($nom) || ! is_string($appel['id'] ?? null)) {
                throw LlmCallFailed::unexpectedPayload('http');
            }

            // Arguments illisibles : on transmet un tableau vide, la validation
            // de l'outil renverra l'erreur au modèle pour qu'il corrige.
            $arguments = json_decode((string) ($appel['function']['arguments'] ?? ''), true);

            $appels[] = new ToolCall($appel['id'], $nom, is_array($arguments) ? $arguments : []);
        }

        $texte = is_string($message['content'] ?? null) ? trim($message['content']) : null;

        if ($appels === [] && ($texte === null || $texte === '')) {
            throw LlmCallFailed::unexpectedPayload('http');
        }

        return AssistantTurn::of($texte, $appels);
    }

    /** @param  array<string, mixed>  $corps */
    private function post(array $corps): Response
    {
        try {
            $reponse = Http::timeout($this->timeout)
                ->when($this->apiKey, fn (PendingRequest $requete) => $requete->withToken((string) $this->apiKey))
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', $corps);
        } catch (ConnectionException $e) {
            throw LlmCallFailed::unreachable('http', $e);
        }

        if ($reponse->failed()) {
            throw LlmCallFailed::rejected('http', $reponse->status());
        }

        return $reponse;
    }

    /**
     * @param  list<Message>  $messages
     * @return list<array<string, mixed>>
     */
    private function wireMessages(array $messages): array
    {
        return array_map(function (Message $message): array {
            if ($message->role === Role::Tool) {
                return ['role' => 'tool', 'tool_call_id' => $message->answers?->id, 'content' => $message->content];
            }

            if ($message->toolCalls !== []) {
                return [
                    'role' => 'assistant',
                    'content' => $message->content,
                    'tool_calls' => array_map(fn (ToolCall $appel): array => [
                        'id' => $appel->id,
                        'type' => 'function',
                        'function' => [
                            'name' => $appel->name,
                            'arguments' => json_encode($appel->arguments === [] ? new \stdClass : $appel->arguments, JSON_UNESCAPED_UNICODE),
                        ],
                    ], $message->toolCalls),
                ];
            }

            return ['role' => $message->role->value, 'content' => $message->content];
        }, $messages);
    }
}
