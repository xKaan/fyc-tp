<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ces tests passent uniquement par les routes HTTP.
 * Ils ne connaissent aucune classe interne de l'application : c'est ce qui leur
 * permet de rester valables quelle que soit l'organisation du code derriere.
 */
class NoteCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_liste_affiche_les_notes(): void
    {
        Note::create(['title' => 'Courses', 'content' => 'Acheter du pain']);
        Note::create(['title' => 'Reunion', 'content' => 'Preparer le support']);

        $reponse = $this->get('/notes');

        $reponse->assertOk();
        $reponse->assertSee('Courses');
        $reponse->assertSee('Reunion');
    }

    public function test_on_peut_consulter_une_note(): void
    {
        $note = Note::create(['title' => 'Courses', 'content' => 'Acheter du pain']);

        $reponse = $this->get('/notes/' . $note->id);

        $reponse->assertOk();
        $reponse->assertSee('Acheter du pain');
    }

    public function test_on_peut_creer_une_note(): void
    {
        $reponse = $this->post('/notes', [
            'title' => 'Idees de projet',
            'content' => 'Une application de prise de notes.',
            'tags' => 'projet,idees',
        ]);

        $reponse->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'title' => 'Idees de projet',
            'content' => 'Une application de prise de notes.',
        ]);
    }

    public function test_le_titre_est_obligatoire(): void
    {
        $reponse = $this->post('/notes', [
            'title' => '',
            'content' => 'Un contenu sans titre.',
        ]);

        $reponse->assertRedirect();
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_le_contenu_est_obligatoire(): void
    {
        $reponse = $this->post('/notes', [
            'title' => 'Un titre sans contenu',
            'content' => '',
        ]);

        $reponse->assertRedirect();
        $this->assertDatabaseCount('notes', 0);
    }

    public function test_on_peut_modifier_une_note(): void
    {
        $note = Note::create(['title' => 'Ancien titre', 'content' => 'Ancien contenu']);

        $reponse = $this->post('/notes/' . $note->id, [
            'title' => 'Nouveau titre',
            'content' => 'Nouveau contenu',
        ]);

        $reponse->assertRedirect();
        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Nouveau titre',
        ]);
    }
}
