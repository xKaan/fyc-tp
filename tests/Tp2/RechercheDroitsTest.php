<?php

namespace Tests\Tp2;

use App\Domain\Ports\NoteHit;
use App\Domain\Ports\NoteSearch;
use App\Models\Note;

/** Étape 2 : la recherche ne lit que les notes autorisées. */
class RechercheDroitsTest extends NoteFlowTestCase
{
    /** @return list<int> */
    private function chercher(string $question, string $prenom): array
    {
        $resultats = $this->app->make(NoteSearch::class)->search($question, $this->user($prenom));

        return array_map(fn (NoteHit $hit): int => $hit->noteId, $resultats);
    }

    public function test_la_question_test_trouve_la_decision_postgresql(): void
    {
        $this->assertContains(
            $this->notePostgres()->id,
            $this->chercher("Qu'a-t-on décidé pour la base de données ?", 'alice'),
        );
    }

    public function test_alice_ne_voit_jamais_les_notes_du_projet_infra(): void
    {
        $this->assertNotContains(
            $this->noteAccesBase()->id,
            $this->chercher("Qu'a-t-on décidé pour la base de données ?", 'alice'),
            'Alice n\'est pas membre du projet Infra : le filtre doit être dans la requête.',
        );
    }

    public function test_chloe_voit_les_notes_infra_mais_pas_celles_de_produit(): void
    {
        $ids = $this->chercher('base de données', 'chloe');

        $this->assertContains($this->noteAccesBase()->id, $ids);
        $this->assertNotContains($this->notePostgres()->id, $ids);
    }

    public function test_une_note_sans_projet_ne_sort_jamais(): void
    {
        Note::create(['title' => 'Base de données perso', 'content' => 'Mes notes sur la base de données.']);

        $titres = array_map(
            fn (NoteHit $hit): string => $hit->title,
            $this->app->make(NoteSearch::class)->search('base de données', $this->user('bruno'), 26),
        );

        $this->assertNotContains('Base de données perso', $titres);
    }
}
