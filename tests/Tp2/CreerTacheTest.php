<?php

namespace Tests\Tp2;

use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\RequiresApproval;
use App\Application\Agent\Tools\CreerTache;
use App\Models\Task;

/** Étape 4 : l'outil creer_tache, qui ne crée rien sans confirmation. */
class CreerTacheTest extends NoteFlowTestCase
{
    private function outil(): CreerTache
    {
        return $this->app->make(CreerTache::class);
    }

    /** @return array<string, mixed> */
    private function arguments(array $autres = []): array
    {
        return [
            'titre' => 'Envoyer la grille tarifaire révisée au client pilote',
            'note_id' => $this->noteClientPilote()->id,
            'echeance' => '2026-12-12',
            ...$autres,
        ];
    }

    public function test_c_est_un_outil_qui_demande_une_confirmation(): void
    {
        $this->assertInstanceOf(RequiresApproval::class, $this->outil());

        $definition = $this->outil()->definition();
        $this->assertSame('creer_tache', $definition->name);
        $this->assertEqualsCanonicalizing(['titre', 'note_id'], $definition->parameters['required']);
        $this->assertSame('integer', $definition->parameters['properties']['note_id']['type']);
    }

    public function test_describe_montre_l_action_exacte_sans_rien_creer(): void
    {
        $avant = Task::count();

        $phrase = $this->outil()->describe($this->arguments(), $this->user('alice'));

        $this->assertStringContainsString('Envoyer la grille tarifaire révisée au client pilote', $phrase);
        $this->assertStringContainsString('Compte-rendu : point avec le client pilote', $phrase);
        $this->assertStringContainsString('12/12/2026', $phrase);
        $this->assertSame($avant, Task::count(), 'describe() ne doit rien écrire.');
    }

    public function test_run_cree_la_tache_liee_a_la_note(): void
    {
        $this->outil()->run($this->arguments(), $this->user('alice'));

        $this->assertDatabaseHas('tasks', [
            'title' => 'Envoyer la grille tarifaire révisée au client pilote',
            'note_id' => $this->noteClientPilote()->id,
            'project_id' => $this->noteClientPilote()->project_id,
            'created_by' => $this->user('alice')->id,
            'status' => 'open',
        ]);
    }

    public function test_une_note_d_un_projet_interdit_est_refusee(): void
    {
        $this->expectException(InvalidToolArguments::class);

        $this->outil()->describe($this->arguments(['note_id' => $this->noteAccesBase()->id]), $this->user('alice'));
    }

    public function test_une_note_inexistante_est_refusee(): void
    {
        $this->expectException(InvalidToolArguments::class);

        $this->outil()->describe($this->arguments(['note_id' => 9999]), $this->user('alice'));
    }

    public function test_une_echeance_mal_formee_est_refusee(): void
    {
        $this->expectException(InvalidToolArguments::class);

        $this->outil()->describe($this->arguments(['echeance' => '12/12/2026']), $this->user('alice'));
    }

    public function test_run_reverifie_les_droits(): void
    {
        $this->expectException(InvalidToolArguments::class);

        try {
            $this->outil()->run($this->arguments(['note_id' => $this->noteAccesBase()->id]), $this->user('alice'));
        } finally {
            $this->assertDatabaseMissing('tasks', ['title' => 'Envoyer la grille tarifaire révisée au client pilote']);
        }
    }
}
