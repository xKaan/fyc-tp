<?php

namespace Tests\Tp2;

use App\Application\Agent\AgentAnswer;
use App\Application\Agent\NoteAgent;
use App\Application\Agent\StopReason;
use App\Domain\Ports\AssistantTurn;
use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\Role;
use App\Domain\Ports\ToolCall;
use App\Domain\Ports\ToolCallingPort;
use App\Infrastructure\Llm\FakeLlmAdapter;
use App\Models\PendingAction;
use App\Models\Task;

/**
 * Étape 5 : la boucle. La doublure joue le rôle du modèle : chaque test écrit
 * à l'avance ce que « répond » le modèle, et vérifie ce que fait la boucle.
 */
class NoteAgentTest extends NoteFlowTestCase
{
    private FakeLlmAdapter $modele;

    private function brancher(AssistantTurn ...$tours): void
    {
        $this->modele = FakeLlmAdapter::scripted(...$tours);
        $this->app->instance(ToolCallingPort::class, $this->modele);
    }

    private function demander(string $question, string $prenom = 'alice'): AgentAnswer
    {
        return $this->app->make(NoteAgent::class)->ask($question, $this->user($prenom));
    }

    private function chercher(string $question, string $id = 'c1'): AssistantTurn
    {
        return AssistantTurn::toolCalls(new ToolCall($id, 'chercher_notes', ['question' => $question]));
    }

    public function test_une_reponse_qui_cite_une_note_trouvee_est_acceptee(): void
    {
        $id = $this->notePostgres()->id;
        $this->brancher(
            $this->chercher('base de données'),
            AssistantTurn::text("L'équipe a gardé PostgreSQL [note:{$id}]."),
        );

        $reponse = $this->demander("Qu'a-t-on décidé pour la base de données ?");

        $this->assertSame(StopReason::Answered, $reponse->reason);
        $this->assertSame([$id], $reponse->sources);
        $this->assertSame(2, $reponse->steps);
    }

    public function test_la_boucle_envoie_la_consigne_les_outils_et_le_resultat_au_modele(): void
    {
        $this->brancher($this->chercher('base de données'), AssistantTurn::text('Je ne sais pas.'));

        $this->demander("Qu'a-t-on décidé pour la base de données ?");

        $premier = $this->modele->turns[0];
        $this->assertSame(Role::System, $premier['messages'][0]->role);
        $this->assertSame(Role::User, $premier['messages'][1]->role);
        $this->assertEqualsCanonicalizing(
            ['chercher_notes', 'lister_taches_ouvertes', 'creer_tache'],
            array_map(fn ($outil) => $outil->name, $premier['tools']),
        );

        $second = $this->modele->turns[1]['messages'];
        $this->assertSame(Role::Assistant, $second[2]->role, 'La demande d\'outil du modèle est remise dans l\'historique.');
        $this->assertSame(Role::Tool, $second[3]->role, 'Le résultat de l\'outil est renvoyé au modèle.');
        $this->assertSame('c1', $second[3]->answers?->id);
        $this->assertStringContainsString('PostgreSQL', $second[3]->content);
    }

    public function test_une_reponse_sans_citation_devient_je_ne_sais_pas(): void
    {
        $this->brancher($this->chercher('base de données'), AssistantTurn::text("L'équipe a gardé PostgreSQL."));

        $reponse = $this->demander("Qu'a-t-on décidé pour la base de données ?");

        $this->assertSame(StopReason::DontKnow, $reponse->reason);
        $this->assertSame(AgentAnswer::DONT_KNOW, $reponse->text);
    }

    public function test_une_note_citee_sans_avoir_ete_renvoyee_est_refusee(): void
    {
        // Le modèle « connaît » l'identifiant d'une note Infra : il ne l'a pas
        // reçue de l'outil, la réponse est refusée.
        $interdite = $this->noteAccesBase()->id;
        $this->brancher($this->chercher('base de données'), AssistantTurn::text("Voir la procédure [note:{$interdite}]."));

        $this->assertSame(StopReason::DontKnow, $this->demander('Comment accéder à la base ?')->reason);
    }

    public function test_le_modele_qui_dit_je_ne_sais_pas_est_respecte(): void
    {
        $this->brancher($this->chercher('licorne'), AssistantTurn::text('Je ne sais pas.'));

        $this->assertSame(StopReason::DontKnow, $this->demander('Qui a vu la licorne ?')->reason);
    }

    public function test_la_boucle_s_arrete_au_plafond_d_etapes(): void
    {
        config(['noteflow.agent.max_steps' => 3]);
        // Un seul tour dans le script : il se répète, le modèle ne s'arrête jamais.
        $this->brancher($this->chercher('base de données'));

        $reponse = $this->demander('Question sans fin');

        $this->assertSame(StopReason::StepLimit, $reponse->reason);
        $this->assertCount(3, $this->modele->turns, 'Exactement max_steps appels au modèle, pas un de plus.');
    }

    public function test_creer_tache_arrete_la_boucle_sans_rien_creer(): void
    {
        $note = $this->noteClientPilote();
        $this->brancher(AssistantTurn::toolCalls(new ToolCall('t1', 'creer_tache', [
            'titre' => 'Envoyer la grille tarifaire révisée',
            'note_id' => $note->id,
            'echeance' => '2026-12-12',
        ])));
        $avant = Task::count();

        $reponse = $this->demander('Crée la tâche pour la grille tarifaire');

        $this->assertSame(StopReason::AwaitingApproval, $reponse->reason);
        $this->assertSame($avant, Task::count(), 'Aucune tâche avant la confirmation.');
        $this->assertCount(1, $this->modele->turns, 'La boucle s\'arrête dès la proposition.');

        $action = $reponse->pendingAction;
        $this->assertInstanceOf(PendingAction::class, $action);
        $this->assertSame(PendingAction::PENDING, $action->status);
        $this->assertSame('creer_tache', $action->tool);
        $this->assertStringContainsString('Envoyer la grille tarifaire révisée', $action->description);
    }

    public function test_un_outil_inconnu_est_signale_au_modele_et_la_boucle_continue(): void
    {
        $this->brancher(
            AssistantTurn::toolCalls(new ToolCall('x1', 'supprimer_note', ['note_id' => 1])),
            AssistantTurn::text('Je ne sais pas.'),
        );

        $this->demander('Supprime la note 1');

        $retour = $this->modele->turns[1]['messages'][3];
        $this->assertSame(Role::Tool, $retour->role);
        $this->assertStringContainsString('supprimer_note', $retour->content);
        $this->assertStringContainsString('erreur', $retour->content);
    }

    public function test_des_arguments_refuses_sont_renvoyes_au_modele(): void
    {
        $this->brancher(
            AssistantTurn::toolCalls(new ToolCall('c1', 'chercher_notes', [])),
            AssistantTurn::text('Je ne sais pas.'),
        );

        $this->demander('Cherche');

        $this->assertStringContainsString('erreur', $this->modele->turns[1]['messages'][3]->content);
    }

    public function test_une_creation_interdite_repart_au_modele_sans_action_en_attente(): void
    {
        $this->brancher(
            AssistantTurn::toolCalls(new ToolCall('t1', 'creer_tache', ['titre' => 'Revoir les accès', 'note_id' => $this->noteAccesBase()->id])),
            AssistantTurn::text('Je ne sais pas.'),
        );

        $reponse = $this->demander('Crée une tâche sur les accès');

        $this->assertNotSame(StopReason::AwaitingApproval, $reponse->reason);
        $this->assertSame(0, PendingAction::count());
        $this->assertStringContainsString('erreur', $this->modele->turns[1]['messages'][3]->content);
    }

    public function test_une_panne_du_modele_remonte_telle_quelle(): void
    {
        $this->app->instance(ToolCallingPort::class, FakeLlmAdapter::failing());

        $this->expectException(LlmCallFailed::class);

        $this->demander('Question');
    }
}
