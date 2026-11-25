<?php

declare(strict_types=1);

namespace App\Application\Agent\Tools;

use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\Tool;
use App\Application\Agent\ToolResult;
use App\Domain\Ports\ToolDefinition;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

/**
 * Outil fourni, à lire avant d'écrire les deux autres : il montre les quatre
 * temps d'un outil.
 *
 * 1. Le contrat (definition) : un nom sans ambiguïté, une description qui
 *    dit quand s'en servir, un schéma JSON strict.
 * 2. La validation des arguments par le code, même si le schéma les décrit :
 *    le schéma guide le modèle, il ne garantit rien.
 * 3. Les droits de l'utilisateur, appliqués dans la requête.
 * 4. Un résultat court, en JSON, avec seulement ce dont le modèle a besoin.
 */
final class ListerTachesOuvertes implements Tool
{
    private const MAX_RESULTS = 20;

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'lister_taches_ouvertes',
            description: 'Liste les tâches encore ouvertes, pour vérifier qu\'une tâche n\'existe pas déjà avant d\'en proposer une. '
                .'Ne renvoie que les tâches des projets de l\'utilisateur.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'projet' => [
                        'type' => 'string',
                        'description' => 'Nom exact du projet pour filtrer, par exemple « Produit ». Facultatif.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        );
    }

    public function run(array $arguments, User $user): ToolResult
    {
        // 2. Validation par le code.
        $validator = Validator::make($arguments, ['projet' => ['nullable', 'string', 'max:100']]);

        if ($validator->fails()) {
            throw InvalidToolArguments::fromValidator($validator);
        }

        // 3. Les droits dans la requête : seulement les projets de l'utilisateur.
        $taches = Task::query()
            ->with('project')
            ->where('status', Task::OPEN)
            ->whereIn('project_id', $user->readableProjectIds())
            ->when(
                isset($arguments['projet']),
                fn ($query) => $query->whereHas('project', fn ($projet) => $projet->where('name', $arguments['projet'])),
            )
            ->orderBy('due_on')
            ->limit(self::MAX_RESULTS)
            ->get();

        // 4. Un résultat court et lisible par le modèle.
        return ToolResult::ok([
            'taches' => $taches->map(fn (Task $tache): array => [
                'id' => $tache->id,
                'titre' => $tache->title,
                'projet' => $tache->project->name,
                'echeance' => $tache->due_on?->format('Y-m-d'),
                'note_id' => $tache->note_id,
            ])->all(),
        ]);
    }
}
