<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // La note qui contenait l'action : l'agent ne crée jamais une
            // tâche sortie de nulle part.
            $table->foreignId('note_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        // Une action proposée par l'agent, en attente de la validation de
        // l'utilisateur. Les arguments sont ceux que l'utilisateur a vus :
        // c'est exactement cela qui sera exécuté s'il confirme.
        Schema::create('pending_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tool', 60);
            $table->json('arguments');
            $table->string('description', 500);
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_actions');
        Schema::dropIfExists('tasks');
    }
};
