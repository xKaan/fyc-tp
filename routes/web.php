<?php

use App\Http\Controllers\NoteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/notes');

// Attention a l'ordre : /notes/create doit etre declaree avant /notes/{note},
// sinon "create" serait pris pour un identifiant de note.
Route::get('/notes', [NoteController::class, 'index'])->name('notes.index');
Route::get('/notes/create', [NoteController::class, 'create'])->name('notes.create');
Route::post('/notes', [NoteController::class, 'store'])->name('notes.store');
Route::get('/notes/{note}', [NoteController::class, 'show'])->name('notes.show');
Route::get('/notes/{note}/edit', [NoteController::class, 'edit'])->name('notes.edit');

// La modification reste en POST : c'est ce que decrivent les tests fournis,
// et ceux-la ne se touchent pas. La suppression, elle, passe en DELETE.
Route::post('/notes/{note}', [NoteController::class, 'update'])->name('notes.update');
Route::delete('/notes/{note}', [NoteController::class, 'destroy'])->name('notes.destroy');

Route::post('/notes/{note}/summarize', [NoteController::class, 'summarize'])->name('notes.summarize');
Route::post('/notes/{note}/tags', [NoteController::class, 'suggestTags'])->name('notes.tags');

Route::get('/tag/{tag}', [NoteController::class, 'tagged'])->name('notes.tagged');
