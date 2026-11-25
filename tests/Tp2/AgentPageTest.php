<?php

namespace Tests\Tp2;

use App\Domain\Ports\AssistantTurn;
use App\Domain\Ports\ToolCall;
use App\Domain\Ports\ToolCallingPort;
use App\Infrastructure\Llm\FakeLlmAdapter;
use App\Models\PendingAction;

/** Tout assemblé : la page de l'assistant, de la question à la confirmation. */
class AgentPageTest extends NoteFlowTestCase
{
    private function brancher(AssistantTurn ...$tours): void
    {
        $this->app->instance(ToolCallingPort::class, FakeLlmAdapter::scripted(...$tours));
    }

    private function proposerTache(): PendingAction
    {
        $this->brancher(AssistantTurn::toolCalls(new ToolCall('t1', 'creer_tache', [
            'titre' => 'Envoyer la grille tarifaire révisée',
            'note_id' => $this->noteClientPilote()->id,
        ])));

        $this->actingAs($this->user('alice'))->post('/agent', ['question' => 'Crée la tâche de la grille tarifaire']);

        return PendingAction::firstOrFail();
    }

    public function test_la_page_demande_une_connexion(): void
    {
        $this->get('/agent')->assertRedirect('/connexion');
    }

    public function test_la_reponse_affiche_la_note_citee_en_lien(): void
    {
        $note = $this->notePostgres();
        $this->brancher(
            AssistantTurn::toolCalls(new ToolCall('c1', 'chercher_notes', ['question' => 'base de données'])),
            AssistantTurn::text("PostgreSQL a été retenu [note:{$note->id}]."),
        );

        $this->actingAs($this->user('alice'))
            ->followingRedirects()
            ->post('/agent', ['question' => "Qu'a-t-on décidé pour la base de données ?"])
            ->assertOk()
            ->assertSee('PostgreSQL a été retenu')
            ->assertSee('/notes/'.$note->id)
            ->assertSee($note->title);
    }

    public function test_la_confirmation_cree_la_tache_une_seule_fois(): void
    {
        $action = $this->proposerTache();
        $this->assertDatabaseCount('tasks', 1);

        $this->actingAs($this->user('alice'))->post("/agent/actions/{$action->id}/confirmer")->assertRedirect('/agent');
        $this->actingAs($this->user('alice'))->post("/agent/actions/{$action->id}/confirmer");

        $this->assertDatabaseCount('tasks', 2);
        $this->assertDatabaseHas('tasks', ['title' => 'Envoyer la grille tarifaire révisée']);
    }

    public function test_un_autre_utilisateur_ne_peut_pas_confirmer(): void
    {
        $action = $this->proposerTache();

        $this->actingAs($this->user('bruno'))->post("/agent/actions/{$action->id}/confirmer")->assertForbidden();

        $this->assertDatabaseMissing('tasks', ['title' => 'Envoyer la grille tarifaire révisée']);
    }

    public function test_annuler_ne_cree_rien(): void
    {
        $action = $this->proposerTache();

        $this->actingAs($this->user('alice'))->post("/agent/actions/{$action->id}/annuler");
        $this->actingAs($this->user('alice'))->post("/agent/actions/{$action->id}/confirmer");

        $this->assertDatabaseMissing('tasks', ['title' => 'Envoyer la grille tarifaire révisée']);
    }

    public function test_une_panne_affiche_un_message_lisible(): void
    {
        $this->app->instance(ToolCallingPort::class, FakeLlmAdapter::failing());

        $this->actingAs($this->user('alice'))
            ->followingRedirects()
            ->post('/agent', ['question' => 'Question'])
            ->assertOk()
            ->assertSee("Le service d'IA n'a pas répondu");
    }
}
