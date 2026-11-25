<?php

declare(strict_types=1);

namespace App\Application\Agent\Tools;

use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\Tool;
use App\Application\Agent\ToolResult;
use App\Domain\Ports\NoteHit;
use App\Domain\Ports\NoteSearch;
use App\Domain\Ports\ToolDefinition;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Outil de recherche : la récupération de S2.3, exposée à l'agent.
 *
 * L'outil ne filtre pas lui-même les droits : il passe l'utilisateur à la
 * recherche, qui filtre dans la requête. Il renvoie l'identifiant de chaque
 * note dans ses sources : ce sont les seules que le modèle pourra citer.
 */
final class ChercherNotes implements Tool
{
    private const LIMIT = 5;

    public function __construct(private readonly NoteSearch $search) {}

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'chercher_notes',
            description: 'Cherche dans les notes de l\'équipe celles qui répondent à une question. '
                .'À appeler avant toute réponse sur le contenu des notes. Renvoie au plus 5 notes, avec leur identifiant à citer.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'question' => [
                        'type' => 'string',
                        'description' => 'La question ou les mots-clés à chercher, en français.',
                    ],
                ],
                'required' => ['question'],
                'additionalProperties' => false,
            ],
        );
    }

    public function run(array $arguments, User $user): ToolResult
    {
        $validator = Validator::make($arguments, ['question' => ['required', 'string', 'min:2', 'max:500']]);

        if ($validator->fails()) {
            throw InvalidToolArguments::fromValidator($validator);
        }

        $notes = $this->search->search($arguments['question'], $user, self::LIMIT);

        if ($notes === []) {
            return ToolResult::ok(['notes' => [], 'message' => 'Aucune note ne correspond à cette recherche.']);
        }

        return ToolResult::ok(
            ['notes' => array_map(fn (NoteHit $note): array => [
                'id' => $note->noteId,
                'titre' => $note->title,
                'projet' => $note->project,
                'extrait' => $note->excerpt,
            ], $notes)],
            sources: array_map(fn (NoteHit $note): int => $note->noteId, $notes),
        );
    }
}
