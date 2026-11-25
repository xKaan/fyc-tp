<?php

declare(strict_types=1);

namespace App\Infrastructure\Llm;

use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;

/**
 * Doublure de test : le troisième adaptateur du port.
 *
 * Aucune requête réseau, une réponse fixe, et la liste des messages reçus
 * pour vérifier ce que le cœur métier a envoyé. Elle sert dans les tests de
 * toutes les séances suivantes, et c'est aussi le fournisseur par défaut
 * (LLM_PROVIDER=fake) : l'application démarre sans clé ni modèle local.
 */
final class FakeLlmAdapter implements LlmPort
{
    /** @var list<list<Message>> */
    public array $calls = [];

    public function __construct(
        private readonly string $answer = 'Réponse simulée par la doublure.',
        private readonly bool $shouldFail = false,
    ) {}

    public static function failing(): self
    {
        return new self(shouldFail: true);
    }

    public function complete(array $messages): string
    {
        $this->calls[] = $messages;

        if ($this->shouldFail) {
            throw LlmCallFailed::unreachable('fake');
        }

        return $this->answer;
    }
}
