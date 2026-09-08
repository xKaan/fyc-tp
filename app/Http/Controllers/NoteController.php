<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Adapters\TextGenerationFailed;
use App\Http\Requests\StoreNoteRequest;
use App\Http\Requests\UpdateNoteRequest;
use App\Models\Note;
use App\UseCases\SuggestTags;
use App\UseCases\SummarizeNote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Le controleur ne fait plus que du HTTP : recevoir une requete, declencher
 * une action, renvoyer une reponse. Il ne sait ni construire un prompt, ni
 * quel fournisseur d'IA est utilise.
 */
class NoteController extends Controller
{
    private const AI_UNAVAILABLE = "Le service d'IA n'a pas repondu. Reessayez dans un instant.";

    public function index(Request $request): View
    {
        $terme = trim((string) $request->input('q'));

        $notes = Note::query()
            ->when($terme !== '', fn (Builder $query) => $query->matchingTitle($terme))
            ->latest()
            ->get();

        return view('notes.index', ['notes' => $notes]);
    }

    public function tagged(string $tag): View
    {
        return view('notes.index', ['notes' => Note::tagged($tag)->latest()->get()]);
    }

    public function create(): View
    {
        return view('notes.create');
    }

    public function store(StoreNoteRequest $request): RedirectResponse
    {
        $note = Note::create($request->validated());

        return redirect()->route('notes.show', $note)->with('status', 'Note creee.');
    }

    public function show(Note $note): View
    {
        return view('notes.show', ['note' => $note]);
    }

    public function edit(Note $note): View
    {
        return view('notes.edit', ['note' => $note]);
    }

    public function update(UpdateNoteRequest $request, Note $note): RedirectResponse
    {
        $note->update($request->validated());

        return redirect()->route('notes.show', $note)->with('status', 'Note mise a jour.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $note->delete();

        return redirect()->route('notes.index')->with('status', 'Note supprimee.');
    }

    public function summarize(Note $note, SummarizeNote $summarize): RedirectResponse
    {
        try {
            $summarize($note);
        } catch (TextGenerationFailed) {
            return back()->withErrors(['ai' => self::AI_UNAVAILABLE]);
        }

        return back()->with('status', 'Resume genere.');
    }

    public function suggestTags(Note $note, SuggestTags $suggestTags): RedirectResponse
    {
        try {
            $suggestTags($note);
        } catch (TextGenerationFailed) {
            return back()->withErrors(['ai' => self::AI_UNAVAILABLE]);
        }

        return back()->with('status', 'Tags suggeres.');
    }
}
