<?php

namespace Tests\Feature;

use App\Domain\Ports\LlmPort;
use App\Infrastructure\Llm\FakeLlmAdapter;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le test que l'architecture de départ interdisait d'écrire.
 *
 * La doublure en panne remplace le vrai fournisseur dans le conteneur : on
 * simule la panne sans connaître ni URL ni format de réponse.
 */
class NoteAiFailureTest extends TestCase
{
    use RefreshDatabase;

    private function note(): Note
    {
        return Note::create(['title' => 'Courses', 'content' => 'Acheter du pain.']);
    }

    public function test_une_panne_du_modele_ne_produit_pas_d_erreur_500(): void
    {
        $this->app->instance(LlmPort::class, FakeLlmAdapter::failing());
        $note = $this->note();

        $reponse = $this->from('/notes/'.$note->id)->post('/notes/'.$note->id.'/summarize');

        $reponse->assertRedirect('/notes/'.$note->id);
        $reponse->assertSessionHasErrors('ai');
    }

    public function test_l_utilisateur_voit_un_message_lisible(): void
    {
        $this->app->instance(LlmPort::class, FakeLlmAdapter::failing());
        $note = $this->note();

        $this->followingRedirects()
            ->from('/notes/'.$note->id)
            ->post('/notes/'.$note->id.'/summarize')
            ->assertOk()
            ->assertSee("Le service d'IA n'a pas repondu.");
    }

    public function test_la_note_n_est_pas_modifiee_quand_le_modele_echoue(): void
    {
        $this->app->instance(LlmPort::class, FakeLlmAdapter::failing());
        $note = $this->note();

        $this->from('/notes/'.$note->id)->post('/notes/'.$note->id.'/tags');

        $this->assertNull($note->fresh()->summary);
        $this->assertNull($note->fresh()->tags);
    }

    public function test_toute_la_chaine_resiste_a_une_erreur_500_du_vrai_fournisseur(): void
    {
        // Ici, pas de doublure : l'adaptateur configuré reçoit une vraie
        // réponse HTTP 500, simulée au niveau du client HTTP.
        Http::fake(['*' => Http::response('service indisponible', 500)]);
        $note = $this->note();

        $this->from('/notes/'.$note->id)
            ->post('/notes/'.$note->id.'/summarize')
            ->assertSessionHasErrors('ai');
    }
}
