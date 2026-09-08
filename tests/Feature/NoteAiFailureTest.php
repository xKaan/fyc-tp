<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ce test est celui que l'architecture de depart interdisait d'ecrire.
 *
 * Avant le refactor, l'acces non garde a la reponse JSON produisait une
 * erreur 500 brute : il n'y avait aucun comportement propre a affirmer.
 */
class NoteAiFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les deux fournisseurs sont en panne : le test reste agnostique.
        Http::fake(['*' => Http::response('service indisponible', 500)]);
    }

    private function note(): Note
    {
        return Note::create(['title' => 'Courses', 'content' => 'Acheter du pain.']);
    }

    public function test_une_panne_du_service_ne_produit_pas_d_erreur_500(): void
    {
        $note = $this->note();

        $reponse = $this->from('/notes/'.$note->id)->post('/notes/'.$note->id.'/summarize');

        $reponse->assertRedirect('/notes/'.$note->id);
        $reponse->assertSessionHasErrors('ai');
    }

    public function test_l_utilisateur_voit_un_message_lisible(): void
    {
        $note = $this->note();

        $this->followingRedirects()
            ->from('/notes/'.$note->id)
            ->post('/notes/'.$note->id.'/summarize')
            ->assertOk()
            ->assertSee("Le service d'IA n'a pas repondu.");
    }

    public function test_la_note_n_est_pas_modifiee_quand_le_service_echoue(): void
    {
        $note = $this->note();

        $this->from('/notes/'.$note->id)->post('/notes/'.$note->id.'/tags');

        $this->assertNull($note->fresh()->summary);
        $this->assertNull($note->fresh()->tags);
    }
}
