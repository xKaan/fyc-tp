<?php

declare(strict_types=1);

namespace App\Application\Agent;

use App\Models\PendingAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Cas d'usage : l'utilisateur confirme l'action proposée par l'agent.
 *
 * On exécute exactement les arguments qu'il a vus, avec ses droits à lui,
 * revérifiés au moment de l'exécution : entre la proposition et le clic, il a
 * pu perdre l'accès au projet.
 */
final class ConfirmPendingAction
{
    public function __construct(private readonly Toolbox $tools) {}

    /**
     * @throws AuthorizationException si l'action appartient à un autre utilisateur
     * @throws ActionAlreadyHandled si elle a déjà été confirmée ou annulée
     * @throws InvalidToolArguments si les droits ou les données ont changé entre-temps
     */
    public function __invoke(PendingAction $action, User $user): ToolResult
    {
        if ($action->user_id !== $user->id) {
            throw new AuthorizationException("Cette action n'est pas la vôtre.");
        }

        $tool = $this->tools->find($action->tool);

        if (! $tool instanceof RequiresApproval) {
            throw new InvalidToolArguments("L'outil [{$action->tool}] ne s'exécute pas par confirmation.");
        }

        // Un double clic ne doit pas créer deux tâches : on ne passe l'action
        // à « approuvée » que si elle est encore en attente, en une requête.
        // La transaction annule ce passage si l'exécution échoue.
        return DB::transaction(function () use ($action, $user, $tool): ToolResult {
            $claimed = PendingAction::query()
                ->whereKey($action->id)
                ->where('status', PendingAction::PENDING)
                ->update(['status' => PendingAction::APPROVED]);

            if ($claimed === 0) {
                throw ActionAlreadyHandled::make();
            }

            return $tool->run($action->arguments, $user);
        });
    }
}
