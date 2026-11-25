<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Domain\Ports\AssistantTurn;
use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;
use App\Domain\Ports\ToolCallingPort;
use App\Domain\Ports\ToolDefinition;

/**
 * Doublure de test : le troisième adaptateur du port.
 *
 * Aucune requête réseau, une réponse fixe, et la liste des messages reçus
 * pour vérifier ce que le cœur métier a envoyé. Elle sert dans les tests de
 * toutes les séances suivantes, et c'est aussi le fournisseur par défaut
 * (LLM_PROVIDER=fake) : l'application démarre sans clé ni modèle local.
 *
 * Séance 2 : scripted() joue une suite de tours écrite à l'avance (appel
 * d'outil, puis réponse finale...). Une fois la suite épuisée, le dernier tour
 * se répète : un script d'un seul appel d'outil simule un modèle qui ne
 * s'arrête jamais.
 */
final class FakeLlmAdapter implements LlmPort, ToolCallingPort
{
    /** @var list<list<Message>> messages reçus par complete() */
    public array $calls = [];

    /** @var list<array{messages: list<Message>, tools: list<ToolDefinition>}> tours reçus par next() */
    public array $turns = [];

    /** @param  list<AssistantTurn>  $script */
    public function __construct(
        private readonly string $answer = 'Réponse simulée par la doublure.',
        private readonly bool $shouldFail = false,
        private array $script = [],
    ) {}

    public static function failing(): self
    {
        return new self(shouldFail: true);
    }

    public static function scripted(AssistantTurn ...$turns): self
    {
        return new self(script: array_values($turns));
    }

    public function complete(array $messages): string
    {
        $this->calls[] = $messages;

        if ($this->shouldFail) {
            throw LlmCallFailed::unreachable('fake');
        }

        return $this->answer;
    }

    public function next(array $messages, array $tools): AssistantTurn
    {
        $this->turns[] = ['messages' => $messages, 'tools' => $tools];

        if ($this->shouldFail) {
            throw LlmCallFailed::unreachable('fake');
        }

        if ($this->script === []) {
            return AssistantTurn::text($this->answer);
        }

        return count($this->script) > 1 ? array_shift($this->script) : $this->script[0];
    }
}
