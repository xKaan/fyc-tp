<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Models\User;

/**
 * Un outil qui modifie des données : la boucle d'agent ne l'exécute jamais
 * directement. Elle s'arrête, montre à l'utilisateur l'action exacte décrite
 * par describe(), et run() n'est appelé qu'après sa confirmation.
 */
interface RequiresApproval extends Tool
{
    /**
     * Vérifie les arguments et les droits, puis décrit en une phrase ce qui
     * sera fait. Ne modifie rien.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws InvalidToolArguments
     */
    public function describe(array $arguments, User $user): string;
}
