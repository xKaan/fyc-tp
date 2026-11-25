<?php

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NoteController extends Controller
{
    public function index(Request $request)
    {
        // On charge tout, on triera apres.
        $notes = Note::all();

        $recherche = $request->input('q');
        if ($recherche != '') {
            $resultat = [];
            foreach ($notes as $n) {
                if (strpos(strtolower($n->title), strtolower($recherche)) !== false) {
                    $resultat[] = $n;
                }
            }
            $notes = collect($resultat);
        }

        $notes = $notes->sortByDesc('created_at');

        return view('notes.index', ['notes' => $notes]);
    }

    public function create()
    {
        return view('notes.create');
    }

    public function store(Request $request)
    {
        // Validation
        if ($request->input('title') == '') {
            return redirect('/notes/create')->with('erreur', 'Le titre est obligatoire.');
        }
        if (strlen($request->input('title')) > 200) {
            return redirect('/notes/create')->with('erreur', 'Le titre est trop long.');
        }
        if ($request->input('content') == '') {
            return redirect('/notes/create')->with('erreur', 'Le contenu est obligatoire.');
        }

        $note = new Note();
        $note->title = $request->input('title');
        $note->content = $request->input('content');
        $note->tags = $request->input('tags');
        $note->save();

        return redirect('/notes/' . $note->id);
    }

    public function show($id)
    {
        $note = Note::where('id', $id)->first();

        return view('notes.show', ['note' => $note]);
    }

    public function edit($id)
    {
        $note = Note::where('id', $id)->first();

        return view('notes.edit', ['note' => $note]);
    }

    public function update($id, Request $request)
    {
        $note = Note::where('id', $id)->first();

        if ($request->input('title') == '') {
            return redirect('/notes/' . $id . '/edit')->with('erreur', 'Le titre est obligatoire.');
        }
        if ($request->input('content') == '') {
            return redirect('/notes/' . $id . '/edit')->with('erreur', 'Le contenu est obligatoire.');
        }

        $note->update($request->except('_token'));

        return redirect('/notes/' . $id);
    }

    public function summarize($id, Request $request)
    {
        $note = Note::where('id', $id)->first();

        Log::info('summarize', $request->all());

        $provider = env('LLM_PROVIDER', 'ollama');

        if ($provider == 'http') {
            $cle = env('LLM_HTTP_API_KEY');

            $reponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $cle,
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Resume la note suivante en 3 phrases maximum, en francais. "
                            . "Ne reponds que le resume, sans introduction.\n\n"
                            . $note->title . "\n" . $note->content,
                    ],
                ],
            ]);

            $resume = $reponse->json()['choices'][0]['message']['content'];
        } else {
            $reponse = Http::post('http://host.docker.internal:11434/api/chat', [
                'model' => 'llama3.2:3b',
                'messages' => [
                    ['role' => 'user', 'content' => "Resume la note suivante en 3 phrases maximum, en francais. "
                        . "Ne reponds que le resume, sans introduction.\n\n"
                        . $note->title . "\n" . $note->content],
                ],
                'stream' => false,
            ]);

            $resume = $reponse->json()['message']['content'];
        }

        // $resume = str_replace('**', '', $resume);
        // $resume = substr($resume, 0, 500);

        $note->summary = $resume;
        $note->save();

        return redirect('/notes/' . $id);
    }

    public function suggestTags($id, Request $request)
    {
        $note = Note::where('id', $id)->first();

        Log::info('tags', $request->all());

        $provider = env('LLM_PROVIDER', 'ollama');

        if ($provider == 'http') {
            $cle = env('LLM_HTTP_API_KEY');

            $reponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $cle,
                'Content-Type' => 'application/json',
            ])->post('https://api.mistral.ai/v1/chat/completions', [
                'model' => 'mistral-small-latest',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => "Donne au maximum 5 tags pour la note suivante. "
                            . "Reponds uniquement avec les tags separes par des virgules.\n\n"
                            . $note->title . "\n" . $note->content,
                    ],
                ],
            ]);

            $texte = $reponse->json()['choices'][0]['message']['content'];
        } else {
            $reponse = Http::post('http://host.docker.internal:11434/api/chat', [
                'model' => 'llama3.2:3b',
                'messages' => [
                    ['role' => 'user', 'content' => "Donne au maximum 5 tags pour la note suivante. "
                        . "Reponds uniquement avec les tags separes par des virgules.\n\n"
                        . $note->title . "\n" . $note->content],
                ],
                'stream' => false,
            ]);

            $texte = $reponse->json()['message']['content'];
        }

        // On nettoie ce que le modele a renvoye.
        $morceaux = explode(',', $texte);
        $tags = [];
        foreach ($morceaux as $morceau) {
            $morceau = trim(strtolower($morceau));
            $morceau = str_replace('#', '', $morceau);
            if ($morceau != '' && count($tags) < 5) {
                $tags[] = $morceau;
            }
        }

        $note->tags = implode(',', $tags);
        $note->save();

        return redirect('/notes/' . $id);
    }
}
