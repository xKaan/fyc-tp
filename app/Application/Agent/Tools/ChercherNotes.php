<?php

declare(strict_types=1);

namespace App\Application\Agent\Tools;

use App\Application\Agent\Tool;
use App\Application\Agent\ToolResult;
use App\Domain\Ports\NoteSearch;
use App\Domain\Ports\ToolDefinition;
use App\Models\User;
use LogicException;

/**
 * Outil de recherche : la récupération de S2.3, exposée à l'agent.
 *
 * À écrire à l'étape 3 du TP, sur le modèle de ListerTachesOuvertes.
 * Tests : tests/Tp2/ChercherNotesTest.php
 */
final class ChercherNotes implements Tool
{
    public function __construct(private readonly NoteSearch $search) {}

    public function definition(): ToolDefinition
    {
        // Nom : chercher_notes. Un seul argument obligatoire, « question ».
        throw new LogicException('À écrire : étape 3 du TP (TP-S2-enonce.md).');
    }

    public function run(array $arguments, User $user): ToolResult
    {
        // Valider, chercher avec $this->search, renvoyer les notes et leurs
        // identifiants en sources.
        throw new LogicException('À écrire : étape 3 du TP (TP-S2-enonce.md).');
    }
}
