<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La route la plus destructrice du projet etait la seule sans test, et elle
 * repondait a une simple requete GET : n'importe quel prefetch de navigateur
 * pouvait supprimer une note.
 */
class NoteDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_suppression_passe_par_le_verbe_delete(): void
    {
        $note = Note::create(['title' => 'Courses', 'content' => 'Acheter du pain.']);

        $this->delete('/notes/'.$note->id)->assertRedirect('/notes');

        $this->assertDatabaseCount('notes', 0);
    }

    public function test_l_ancien_lien_de_suppression_en_get_n_existe_plus(): void
    {
        $note = Note::create(['title' => 'Courses', 'content' => 'Acheter du pain.']);

        $this->get('/notes/'.$note->id.'/delete')->assertNotFound();

        $this->assertDatabaseCount('notes', 1);
    }

    public function test_supprimer_une_note_inexistante_renvoie_404(): void
    {
        $this->delete('/notes/999')->assertNotFound();
    }
}
