<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NoteFlow, fin de séance 2 : un vecteur par note, pour la recherche par
 * similarité.
 *
 * Sous PostgreSQL, la colonne est de type vector(N), avec l'extension
 * pgvector. Sous SQLite (les tests), le vecteur est gardé en texte, au même
 * format : l'indexation se teste partout, la recherche seulement sur
 * PostgreSQL.
 *
 * Pas d'index approximatif (HNSW) : pour 26 notes, la recherche exacte est
 * instantanée, et elle donne le recall de référence (S2.3).
 */
return new class extends Migration
{
    public function up(): void
    {
        $postgres = Schema::getConnection()->getDriverName() === 'pgsql';

        if ($postgres) {
            Schema::ensureVectorExtensionExists();
        }

        Schema::create('note_embeddings', function (Blueprint $table) use ($postgres) {
            // Les notes font moins de 300 mots : un seul morceau par note.
            // Des notes longues demanderaient un découpage, donc une ligne
            // par morceau et une clé (note_id, rang).
            $table->foreignId('note_id')->primary()->constrained()->cascadeOnDelete();
            // Le modèle qui a produit le vecteur : on ne compare jamais un
            // vecteur de la question à un vecteur d'un autre modèle.
            $table->string('model', 120);
            // Empreinte du texte vectorisé : réindexer ne recalcule que les
            // notes modifiées.
            $table->string('content_hash', 64);

            if ($postgres) {
                $table->vector('embedding', (int) config('llm.embeddings.dimensions'));
            } else {
                $table->text('embedding');
            }

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('note_embeddings');
    }
};
