<?php

declare(strict_types=1);

namespace App\Application\Agent;

/**
 * Pourquoi la boucle s'est arrêtée. Une boucle d'agent s'arrête toujours sur
 * une raison connue, jamais par hasard (S2.2).
 */
enum StopReason: string
{
    /** Le modèle a répondu en citant des notes qu'il a vraiment reçues. */
    case Answered = 'answered';

    /** Aucune note ne permet de répondre, ou la réponse ne cite pas de source valable. */
    case DontKnow = 'dont_know';

    /** Le modèle propose une écriture : l'utilisateur doit confirmer. */
    case AwaitingApproval = 'awaiting_approval';

    /** Le plafond d'étapes est atteint sans réponse finale. */
    case StepLimit = 'step_limit';
}
