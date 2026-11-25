<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Domain\Ports\ToolDefinition;
use App\Models\User;

/**
 * Le contrat d'un outil côté application (S2.1).
 *
 * definition() est ce que voit le modèle. run() est ce que fait le code, et
 * c'est le code qui décide : il valide les arguments, vérifie les droits de
 * l'utilisateur qui pose la question, puis exécute. Le modèle n'exécute
 * jamais rien lui-même.
 */
interface Tool
{
    public function definition(): ToolDefinition;

    /**
     * @param  array<string, mixed>  $arguments  les arguments proposés par le modèle, non vérifiés
     *
     * @throws InvalidToolArguments si les arguments ou les droits ne permettent pas l'appel
     */
    public function run(array $arguments, User $user): ToolResult;
}
