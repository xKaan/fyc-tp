<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\Prompt;
use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\Message;
use App\Domain\Ports\ToolCallingPort;
use App\Models\PendingAction;
use App\Models\User;

/**
 * Cas d'usage : l'agent de NoteFlow répond à une question de l'utilisateur.
 *
 * C'est le « harness » : la boucle qui entoure le modèle. Le modèle propose
 * (un appel d'outil ou une réponse), le code décide : il exécute les outils
 * avec les droits de l'utilisateur, s'arrête au plafond d'étapes, met les
 * écritures en attente de confirmation et refuse une réponse sans source.
 */
final class NoteAgent
{
    public function __construct(
        private readonly ToolCallingPort $llm,
        private readonly Prompt $prompt,
        private readonly Toolbox $tools,
        private readonly int $maxSteps,
    ) {}

    /** @throws LlmCallFailed */
    public function ask(string $question, User $user): AgentAnswer
    {
        $messages = [Message::system($this->prompt->text), Message::user($question)];
        $sources = [];

        for ($step = 1; $step <= $this->maxSteps; $step++) {
            $turn = $this->llm->next($messages, $this->tools->definitions());

            // Pas d'appel d'outil : le modèle a fini, on vérifie sa réponse.
            if (! $turn->hasToolCalls()) {
                return $this->finish((string) $turn->text, $sources, $step);
            }

            $messages[] = Message::assistantToolCalls($turn);

            foreach ($turn->toolCalls as $call) {
                $tool = $this->tools->find($call->name);

                if ($tool === null) {
                    $result = ToolResult::error("Outil inconnu : {$call->name}. Outils disponibles : ".implode(', ', $this->tools->names()).'.');
                } else {
                    try {
                        // Une écriture ne s'exécute pas ici : on s'arrête et on
                        // montre l'action exacte à l'utilisateur.
                        if ($tool instanceof RequiresApproval) {
                            $description = $tool->describe($call->arguments, $user);

                            return AgentAnswer::awaitingApproval(PendingAction::propose($user, $call, $description), $step);
                        }

                        $result = $tool->run($call->arguments, $user);
                    } catch (InvalidToolArguments $e) {
                        // L'erreur repart au modèle, qui peut corriger son appel.
                        $result = ToolResult::error($e->getMessage());
                    }
                }

                $sources = [...$sources, ...$result->sources];
                $messages[] = Message::toolResult($call, $result->toJson());
            }
        }

        return AgentAnswer::stepLimitReached($this->maxSteps);
    }

    /**
     * Une réponse n'est acceptée que si elle cite au moins une note, et
     * seulement des notes renvoyées par un outil pendant cette exécution.
     * Sinon, c'est « je ne sais pas » : une note inventée, ou lue ailleurs
     * que dans les résultats autorisés, ne passe pas.
     *
     * @param  list<int>  $sources
     */
    private function finish(string $text, array $sources, int $step): AgentAnswer
    {
        $cited = Citations::extract($text);

        if (Citations::saysDontKnow($text) || $cited === [] || array_diff($cited, $sources) !== []) {
            return AgentAnswer::dontKnow($step);
        }

        return AgentAnswer::answered(trim($text), $cited, $step);
    }
}
