<?php

use App\Http\Controllers\NoteController;
use App\Models\Note;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/notes');
});

Route::get('/notes', [NoteController::class, 'index']);
Route::get('/notes/create', [NoteController::class, 'create']);
Route::post('/notes', [NoteController::class, 'store']);
Route::get('/notes/{id}', [NoteController::class, 'show']);
Route::get('/notes/{id}/edit', [NoteController::class, 'edit']);
Route::post('/notes/{id}', [NoteController::class, 'update']);
Route::post('/notes/{id}/summarize', [NoteController::class, 'summarize']);
Route::post('/notes/{id}/tags', [NoteController::class, 'suggestTags']);

// Suppression : c'est juste une ligne, pas la peine de passer par le controleur.
Route::get('/notes/{id}/delete', function ($id) {
    $note = Note::where('id', $id)->first();
    $note->delete();

    return redirect('/notes');
});

// Filtrer les notes qui portent un tag donne.
Route::get('/tag/{tag}', function ($tag) {
    $notes = Note::all();

    $resultat = [];
    foreach ($notes as $note) {
        $liste = explode(',', $note->tags);
        foreach ($liste as $t) {
            if (trim($t) == $tag) {
                $resultat[] = $note;
            }
        }
    }

    return view('notes.index', ['notes' => collect($resultat)]);
});
