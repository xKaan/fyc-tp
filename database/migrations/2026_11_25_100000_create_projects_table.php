<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NoteFlow, séance 2 : les notes sont rangées par projet, et le droit de
 * lire une note vient de l'appartenance au projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        // Qui est membre de quel projet. Être membre donne le droit de lire
        // les notes du projet et d'y créer des tâches.
        Schema::create('project_user', function (Blueprint $table) {
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['project_id', 'user_id']);
        });

        // Les notes de la séance 1 n'ont pas de projet : la colonne reste
        // facultative. Une note sans projet n'est lisible par aucun agent.
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('author_id');
            $table->dropConstrainedForeignId('project_id');
        });
        Schema::dropIfExists('project_user');
        Schema::dropIfExists('projects');
    }
};
