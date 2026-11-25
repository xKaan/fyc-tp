<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Agent\ActionAlreadyHandled;
use App\Application\Agent\AgentAnswer;
use App\Application\Agent\ConfirmPendingAction;
use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\NoteAgent;
use App\Domain\Ports\LlmCallFailed;
use App\Models\Note;
use App\Models\PendingAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Le contrôleur de l'agent ne fait que du HTTP, comme celui des notes depuis
 * la séance 1 : il ne connaît ni le fournisseur, ni la boucle, ni les outils.
 */
class AgentController extends Controller
{
    private const AI_UNAVAILABLE = "Le service d'IA n'a pas répondu. Réessayez dans un instant.";

    public function show(Request $request): View
    {
        return view('agent.show', ['answer' => $request->session()->get('agent')]);
    }

    public function ask(Request $request, NoteAgent $agent): RedirectResponse
    {
        $question = $request->validate(['question' => ['required', 'string', 'max:500']])['question'];

        try {
            $answer = $agent->ask($question, $request->user());
        } catch (LlmCallFailed) {
            return redirect()->route('agent.show')->withInput()->withErrors(['ai' => self::AI_UNAVAILABLE]);
        }

        return redirect()->route('agent.show')->with('agent', $this->present($question, $answer));
    }

    public function confirm(Request $request, PendingAction $action, ConfirmPendingAction $confirm): RedirectResponse
    {
        try {
            $confirm($action, $request->user());
        } catch (ActionAlreadyHandled|InvalidToolArguments $e) {
            return redirect()->route('agent.show')->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('agent.show')->with('status', 'Fait : '.$action->description);
    }

    public function cancel(Request $request, PendingAction $action): RedirectResponse
    {
        abort_unless($action->user_id === $request->user()->id, 403);

        $action->where('id', $action->id)->where('status', PendingAction::PENDING)->update(['status' => PendingAction::CANCELLED]);

        return redirect()->route('agent.show')->with('status', 'Action annulée, rien n\'a été créé.');
    }

    /**
     * Prépare l'affichage. Le texte du modèle est échappé, puis seules les
     * citations [note:ID] deviennent des liens : aucun HTML ni Markdown venant
     * du modèle n'est interprété, donc aucune image externe ne s'affiche
     * (on y revient en séance 4).
     *
     * @return array<string, mixed>
     */
    private function present(string $question, AgentAnswer $answer): array
    {
        $html = preg_replace_callback(
            '/\[note:(\d+)\]/i',
            fn (array $m): string => '<a href="'.e(route('notes.show', (int) $m[1])).'">[note '.(int) $m[1].']</a>',
            e($answer->text),
        );

        return [
            'question' => $question,
            'reason' => $answer->reason->value,
            'html' => nl2br((string) $html),
            'steps' => $answer->steps,
            'sources' => Note::query()->whereKey($answer->sources)->pluck('title', 'id')->all(),
            'pending_id' => $answer->pendingAction?->id,
        ];
    }
}
