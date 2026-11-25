<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Application\Prompt;
use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\ToolCallingPort;
use App\Models\User;
use LogicException;

/**
 * Cas d'usage : l'agent de NoteFlow répond à une question de l'utilisateur.
 *
 * C'est le « harness » : la boucle qui entoure le modèle. Le modèle propose
 * (un appel d'outil ou une réponse), le code décide : il exécute les outils
 * avec les droits de l'utilisateur, s'arrête au plafond d'étapes, met les
 * écritures en attente de confirmation et refuse une réponse sans source.
 *
 * À écrire à l'étape 5 du TP. Tests : tests/Tp2/NoteAgentTest.php
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
        // 1. L'historique part avec la consigne (system) et la question (user).
        // 2. Au plus $this->maxSteps appels à $this->llm->next().
        // 3. Pas d'appel d'outil : vérifier la réponse et s'arrêter.
        // 4. Sinon, pour chaque appel : outil inconnu ou arguments refusés,
        //    l'erreur repart au modèle ; outil qui écrit, on s'arrête sur une
        //    action en attente ; sinon on exécute et on renvoie le résultat.
        // 5. Plafond atteint : on s'arrête aussi.
        throw new LogicException('À écrire : étape 5 du TP (TP-S2-enonce.md).');
    }
}
