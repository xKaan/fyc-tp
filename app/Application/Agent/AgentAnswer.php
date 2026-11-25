<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Models\PendingAction;

/** Ce que la boucle renvoie au contrôleur : une raison d'arrêt et de quoi l'afficher. */
final class AgentAnswer
{
    public const DONT_KNOW = 'Je ne sais pas : aucune note que vous pouvez lire ne permet de répondre.';

    public const STEP_LIMIT = "L'assistant n'a pas trouvé de réponse dans le nombre d'étapes autorisé.";

    /** @param  list<int>  $sources */
    private function __construct(
        public readonly StopReason $reason,
        public readonly string $text,
        public readonly int $steps,
        public readonly array $sources = [],
        public readonly ?PendingAction $pendingAction = null,
    ) {}

    /** @param  list<int>  $sources  les notes citées, toutes renvoyées par un outil pendant cette exécution */
    public static function answered(string $text, array $sources, int $steps): self
    {
        return new self(StopReason::Answered, $text, $steps, array_values(array_unique($sources)));
    }

    public static function dontKnow(int $steps): self
    {
        return new self(StopReason::DontKnow, self::DONT_KNOW, $steps);
    }

    public static function awaitingApproval(PendingAction $action, int $steps): self
    {
        return new self(StopReason::AwaitingApproval, $action->description, $steps, pendingAction: $action);
    }

    public static function stepLimitReached(int $steps): self
    {
        return new self(StopReason::StepLimit, self::STEP_LIMIT, $steps);
    }
}
