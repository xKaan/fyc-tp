<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Un message envoyé au modèle, dans le vocabulaire de l'application.
 *
 * Les constructeurs nommés empêchent de se tromper de rôle, et chaque
 * adaptateur traduit ce message dans le format JSON de son fournisseur.
 *
 * Séance 2 : deux constructeurs de plus pour l'historique d'une boucle
 * d'agent. assistantToolCalls() rejoue la demande d'outils du modèle,
 * toolResult() lui renvoie le résultat d'un appel. Les champs ajoutés sont
 * facultatifs : le code de la séance 1 ne change pas.
 */
final class Message
{
    /**
     * @param  list<ToolCall>  $toolCalls  les appels demandés par le modèle (rôle assistant)
     * @param  ToolCall|null  $answers  l'appel auquel ce message répond (rôle tool)
     */
    private function __construct(
        public readonly Role $role,
        public readonly string $content,
        public readonly array $toolCalls = [],
        public readonly ?ToolCall $answers = null,
    ) {}

    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    public static function user(string $content): self
    {
        return new self(Role::User, $content);
    }

    public static function assistant(string $content): self
    {
        return new self(Role::Assistant, $content);
    }

    /** Le tour du modèle qui demande des outils, tel qu'il faut le remettre dans l'historique. */
    public static function assistantToolCalls(AssistantTurn $turn): self
    {
        return new self(Role::Assistant, (string) $turn->text, $turn->toolCalls);
    }

    /** Le résultat d'un appel d'outil, renvoyé au modèle. */
    public static function toolResult(ToolCall $call, string $content): self
    {
        return new self(Role::Tool, $content, answers: $call);
    }
}
