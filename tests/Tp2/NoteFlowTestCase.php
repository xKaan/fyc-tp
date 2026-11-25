<?php

namespace Tests\Tp2;

use App\Models\Note;
use App\Models\User;
use Database\Seeders\NoteFlowSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Base des tests du TP S2.4 : une base remplie avec les données du fil rouge.
 * Alice lit Produit et Équipe ; Chloé lit Équipe et Infra.
 */
abstract class NoteFlowTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(NoteFlowSeeder::class);
    }

    protected function user(string $prenom): User
    {
        return User::where('email', "{$prenom}@noteflow.test")->firstOrFail();
    }

    protected function note(string $titre): Note
    {
        return Note::where('title', $titre)->firstOrFail();
    }

    protected function notePostgres(): Note
    {
        return $this->note('Décision : choix de PostgreSQL plutôt que MongoDB');
    }

    protected function noteAccesBase(): Note
    {
        return $this->note('Process : gestion des accès à la base de données');
    }

    protected function noteClientPilote(): Note
    {
        return $this->note('Compte-rendu : point avec le client pilote');
    }
}
