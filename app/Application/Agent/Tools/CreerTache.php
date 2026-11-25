<?php

declare(strict_types=1);

namespace App\Application\Agent\Tools;

use App\Application\Agent\RequiresApproval;
use App\Application\Agent\ToolResult;
use App\Domain\Ports\ToolDefinition;
use App\Models\User;
use LogicException;

/**
 * Le seul outil qui écrit. La boucle ne l'exécute pas : elle appelle
 * describe(), montre la phrase à l'utilisateur et s'arrête. run() n'est
 * appelé qu'après sa confirmation, par ConfirmPendingAction (fourni).
 *
 * À écrire à l'étape 4 du TP. Tests : tests/Tp2/CreerTacheTest.php
 */
final class CreerTache implements RequiresApproval
{
    public function definition(): ToolDefinition
    {
        // Nom : creer_tache. Arguments : titre et note_id obligatoires,
        // echeance (AAAA-MM-JJ) facultative.
        throw new LogicException('À écrire : étape 4 du TP (TP-S2-enonce.md).');
    }

    public function describe(array $arguments, User $user): string
    {
        // Valider, vérifier que $user peut lire la note, décrire l'action
        // exacte. Ne rien écrire.
        throw new LogicException('À écrire : étape 4 du TP (TP-S2-enonce.md).');
    }

    public function run(array $arguments, User $user): ToolResult
    {
        // Revérifier comme describe(), puis créer la tâche.
        throw new LogicException('À écrire : étape 4 du TP (TP-S2-enonce.md).');
    }
}
