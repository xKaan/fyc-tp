<?php

use App\Infrastructure\Search\NoteIndexer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('noteflow:index', function (NoteIndexer $indexer) {
    $resultat = $indexer->indexAll();

    $this->info("{$resultat['indexed']} note(s) vectorisée(s), {$resultat['skipped']} déjà à jour.");
})->purpose('Vectorise les notes pour la recherche par similarité (NOTEFLOW_SEARCH=vector)');
