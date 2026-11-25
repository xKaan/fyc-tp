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
use Illuminate\Http\Client\Response;
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
 *
 * Séance 2 : les appels d'outils d'Ollama n'ont pas toujours d'identifiant,
 * leurs arguments arrivent en objet, et le résultat se renvoie avec
 * `tool_name`. L'adaptateur fabrique un identifiant quand il manque, pour que
 * la boucle d'agent manipule le même ToolCall quel que soit le fournisseur.
 */
final class OllamaAdapter implements LlmPort, ToolCallingPort
{
    public function __construct(
        private readonly string $host,
        private readonly string $model,
        private readonly int $timeout,
        private readonly ?int $contextLength = null,
    ) {}

    public function complete(array $messages): string
    {
        $texte = $this->post($this->body($messages))->json('message.content');

        if (! is_string($texte) || trim($texte) === '') {
            throw LlmCallFailed::unexpectedPayload('ollama');
        }

        return trim($texte);
    }

    public function next(array $messages, array $tools): AssistantTurn
    {
        $corps = $this->body($messages);

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

        $message = $this->post($corps)->json('message');

        if (! is_array($message)) {
            throw LlmCallFailed::unexpectedPayload('ollama');
        }

        $appels = [];

        foreach (array_values($message['tool_calls'] ?? []) as $rang => $appel) {
            $nom = $appel['function']['name'] ?? null;

            if (! is_string($nom)) {
                throw LlmCallFailed::unexpectedPayload('ollama');
            }

            $arguments = $appel['function']['arguments'] ?? [];

            // Selon le modèle, les arguments arrivent en objet ou en chaîne JSON.
            if (is_string($arguments)) {
                $arguments = json_decode($arguments, true);
            }

            $id = is_string($appel['id'] ?? null) ? $appel['id'] : 'ollama-'.($rang + 1);

            $appels[] = new ToolCall($id, $nom, is_array($arguments) ? $arguments : []);
        }

        $texte = is_string($message['content'] ?? null) ? trim($message['content']) : null;

        if ($appels === [] && ($texte === null || $texte === '')) {
            throw LlmCallFailed::unexpectedPayload('ollama');
        }

        return AssistantTurn::of($texte, $appels);
    }

    /**
     * @param  list<Message>  $messages
     * @return array<string, mixed>
     */
    private function body(array $messages): array
    {
        $corps = [
            'model' => $this->model,
            'messages' => array_map(fn (Message $message): array => $this->wireMessage($message), $messages),
            'stream' => false,
        ];

        if ($this->contextLength !== null) {
            $corps['options'] = ['num_ctx' => $this->contextLength];
        }

        return $corps;
    }

    /** @return array<string, mixed> */
    private function wireMessage(Message $message): array
    {
        if ($message->role === Role::Tool) {
            return ['role' => 'tool', 'tool_name' => $message->answers?->name, 'content' => $message->content];
        }

        if ($message->toolCalls !== []) {
            return [
                'role' => 'assistant',
                'content' => $message->content,
                'tool_calls' => array_map(fn (ToolCall $appel): array => [
                    'function' => [
                        'name' => $appel->name,
                        'arguments' => $appel->arguments === [] ? new \stdClass : $appel->arguments,
                    ],
                ], $message->toolCalls),
            ];
        }

        return ['role' => $message->role->value, 'content' => $message->content];
    }

    /** @param  array<string, mixed>  $corps */
    private function post(array $corps): Response
    {
        try {
            $reponse = Http::timeout($this->timeout)
                ->post(rtrim($this->host, '/').'/api/chat', $corps);
        } catch (ConnectionException $e) {
            throw LlmCallFailed::unreachable('ollama', $e);
        }

        if ($reponse->failed()) {
            throw LlmCallFailed::rejected('ollama', $reponse->status());
        }

        return $reponse;
    }
}
