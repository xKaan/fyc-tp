<?php

namespace Tests\Tp2;

use App\Application\Agent\InvalidToolArguments;
use App\Application\Agent\Tools\ChercherNotes;

/** Étape 3 : l'outil chercher_notes. */
class ChercherNotesTest extends NoteFlowTestCase
{
    private function outil(): ChercherNotes
    {
        return $this->app->make(ChercherNotes::class);
    }

    public function test_le_contrat_exige_une_question(): void
    {
        $definition = $this->outil()->definition();

        $this->assertSame('chercher_notes', $definition->name);
        $this->assertNotSame('', trim($definition->description));
        $this->assertSame('object', $definition->parameters['type']);
        $this->assertSame('string', $definition->parameters['properties']['question']['type']);
        $this->assertSame(['question'], $definition->parameters['required']);
    }

    public function test_il_renvoie_les_notes_avec_leur_identifiant_et_les_declare_en_sources(): void
    {
        $resultat = $this->outil()->run(['question' => 'base de données'], $this->user('alice'));

        $ids = array_column($resultat->data['notes'], 'id');

        $this->assertContains($this->notePostgres()->id, $ids);
        $this->assertSame($ids, $resultat->sources, 'Les sources sont exactement les notes montrées au modèle.');
        $this->assertArrayHasKey('titre', $resultat->data['notes'][0]);
        $this->assertArrayHasKey('extrait', $resultat->data['notes'][0]);
    }

    public function test_aucun_resultat_n_est_pas_une_erreur(): void
    {
        $resultat = $this->outil()->run(['question' => 'licorne zeppelin'], $this->user('alice'));

        $this->assertFalse($resultat->isError());
        $this->assertSame([], $resultat->data['notes']);
        $this->assertSame([], $resultat->sources);
    }

    public function test_une_question_absente_est_refusee(): void
    {
        $this->expectException(InvalidToolArguments::class);

        $this->outil()->run([], $this->user('alice'));
    }

    public function test_une_question_qui_n_est_pas_du_texte_est_refusee(): void
    {
        $this->expectException(InvalidToolArguments::class);

        $this->outil()->run(['question' => ['base', 'données']], $this->user('alice'));
    }
}
