<?php

declare(strict_types=1);

namespace App\Application\Agent\Tools;

use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\RequiresApproval;
use App\Application\Agent\ToolResult;
use App\Domain\Ports\ToolDefinition;
use App\Models\Note;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Le seul outil qui écrit. La boucle ne l'exécute pas : elle appelle
 * describe(), montre la phrase à l'utilisateur et s'arrête. run() n'est
 * appelé qu'après sa confirmation, par ConfirmPendingAction.
 */
final class CreerTache implements RequiresApproval
{
    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: 'creer_tache',
            description: 'Propose de créer une tâche à partir d\'une action notée dans une note. '
                .'L\'utilisateur doit confirmer : la tâche n\'existe pas tant qu\'il n\'a pas validé. '
                .'Vérifier d\'abord avec lister_taches_ouvertes qu\'elle n\'existe pas déjà.',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'titre' => [
                        'type' => 'string',
                        'description' => 'Titre court de la tâche, à l\'infinitif, 120 caractères au plus.',
                    ],
                    'note_id' => [
                        'type' => 'integer',
                        'description' => 'Identifiant de la note qui contient l\'action, tel que renvoyé par chercher_notes.',
                    ],
                    'echeance' => [
                        'type' => 'string',
                        'description' => 'Échéance au format AAAA-MM-JJ, si la note en donne une. Facultatif.',
                    ],
                ],
                'required' => ['titre', 'note_id'],
                'additionalProperties' => false,
            ],
        );
    }

    public function describe(array $arguments, User $user): string
    {
        [$note, $echeance] = $this->check($arguments, $user);

        return sprintf(
            'Créer la tâche « %s » dans le projet %s, liée à la note « %s », %s.',
            trim($arguments['titre']),
            $note->project->name,
            $note->title,
            $echeance ? 'échéance le '.$echeance->format('d/m/Y') : 'sans échéance',
        );
    }

    public function run(array $arguments, User $user): ToolResult
    {
        // On revérifie tout : les droits ont pu changer depuis la proposition.
        [$note, $echeance] = $this->check($arguments, $user);

        $tache = Task::create([
            'project_id' => $note->project_id,
            'note_id' => $note->id,
            'created_by' => $user->id,
            'title' => trim($arguments['titre']),
            'due_on' => $echeance,
            'status' => Task::OPEN,
        ]);

        return ToolResult::ok(['tache' => ['id' => $tache->id, 'titre' => $tache->title]]);
    }

    /** @return array{0: Note, 1: Carbon|null} */
    private function check(array $arguments, User $user): array
    {
        $validator = Validator::make($arguments, [
            'titre' => ['required', 'string', 'min:3', 'max:120'],
            'note_id' => ['required', 'integer'],
            'echeance' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            throw InvalidToolArguments::fromValidator($validator);
        }

        $note = Note::with('project')->find($arguments['note_id']);

        // Même message pour une note inexistante et pour une note interdite :
        // la réponse ne doit pas révéler qu'une note existe ailleurs.
        if ($note === null || ! $user->canReadNote($note)) {
            throw new InvalidToolArguments("Arguments refusés : la note {$arguments['note_id']} est introuvable.");
        }

        $echeance = isset($arguments['echeance']) ? Carbon::createFromFormat('Y-m-d', $arguments['echeance'])->startOfDay() : null;

        return [$note, $echeance];
    }
}
