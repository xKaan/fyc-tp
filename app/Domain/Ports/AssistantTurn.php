<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Ce que le modèle renvoie à un tour de boucle : soit du texte (il a fini),
 * soit des demandes d'appel d'outils (il attend leurs résultats).
 */
final class AssistantTurn
{
    /** @param  list<ToolCall>  $toolCalls */
    private function __construct(
        public readonly ?string $text,
        public readonly array $toolCalls,
    ) {}

    public static function text(string $text): self
    {
        return new self($text, []);
    }

    public static function toolCalls(ToolCall ...$calls): self
    {
        return new self(null, array_values($calls));
    }

    /** Pour un adaptateur qui reçoit à la fois du texte et des appels d'outils. */
    public static function of(?string $text, array $calls): self
    {
        return new self($text, array_values($calls));
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== [];
    }
}
