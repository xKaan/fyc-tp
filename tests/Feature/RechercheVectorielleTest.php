<?php

namespace Tests\Feature;

use App\Domain\Ports\NoteHit;
use App\Domain\Ports\NoteSearch;
use App\Infrastructure\Search\KeywordNoteSearch;
use App\Infrastructure\Search\NoteIndexer;
use App\Infrastructure\Search\PgvectorNoteSearch;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\Tp2\NoteFlowTestCase;

/**
 * Corrigé S2.4 : la recherche par similarité, branchée sur le même port que
 * la recherche par mots-clés.
 *
 * L'indexation se teste sur toute base. La recherche demande PostgreSQL avec
 * pgvector : ces tests sont ignorés sous SQLite. Pour les lancer :
 *
 *   docker compose exec pgsql createdb -U sail testing
 *   docker compose exec -u sail -e DB_CONNECTION=pgsql -e DB_HOST=pgsql \
 *     -e DB_DATABASE=testing laravel.test php artisan test
 *
 * La doublure de vectorisation sert partout : un vrai modèle donnerait un
 * classement différent d'un modèle à l'autre, et c'est le rôle du recall@5
 * de S2.3 de le mesurer, pas celui d'un test.
 */
class RechercheVectorielleTest extends NoteFlowTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['llm.embeddings.provider' => 'fake']);
    }

    public function test_l_indexation_vectorise_chaque_note_rangee_dans_un_projet(): void
    {
        $resultat = $this->app->make(NoteIndexer::class)->indexAll();

        $this->assertSame(['indexed' => 26, 'skipped' => 0], $resultat);
        $this->assertSame(26, DB::table('note_embeddings')->count());
        $this->assertSame('fake:hash-768', DB::table('note_embeddings')->value('model'));
    }

    public function test_relancer_l_indexation_ne_recalcule_que_les_notes_modifiees(): void
    {
        $indexeur = $this->app->make(NoteIndexer::class);
        $indexeur->indexAll();

        $this->notePostgres()->update(['content' => 'Contenu revu après la rétrospective.']);

        $this->assertSame(['indexed' => 1, 'skipped' => 25], $indexeur->indexAll());
    }

    public function test_par_defaut_la_recherche_reste_par_mots_cles(): void
    {
        $this->assertInstanceOf(KeywordNoteSearch::class, $this->app->make(NoteSearch::class));
    }

    public function test_la_recherche_vectorielle_refuse_une_base_sans_pgvector(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $this->markTestSkipped('Vérifie le refus hors PostgreSQL.');
        }

        config(['noteflow.search' => 'vector']);

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(NoteSearch::class);
    }

    public function test_la_question_test_trouve_la_decision_postgresql(): void
    {
        $this->assertContains($this->notePostgres()->id, $this->chercherApresIndexation("Qu'a-t-on décidé pour la base de données ?", 'alice'));
    }

    public function test_alice_ne_voit_jamais_les_notes_du_projet_infra(): void
    {
        $ids = $this->chercherApresIndexation('Comment obtenir un accès à la base de données de production ?', 'alice', 26);

        $this->assertNotContains($this->noteAccesBase()->id, $ids);
        $this->assertCount(DB::table('notes')->whereIn('project_id', $this->user('alice')->readableProjectIds())->count(), $ids);
    }

    public function test_chloe_voit_les_notes_infra_mais_pas_celles_de_produit(): void
    {
        $ids = $this->chercherApresIndexation('base de données', 'chloe', 26);

        $this->assertContains($this->noteAccesBase()->id, $ids);
        $this->assertNotContains($this->notePostgres()->id, $ids);
    }

    public function test_un_vecteur_d_un_autre_modele_n_est_jamais_compare(): void
    {
        $this->assertNotSame([], $this->chercherApresIndexation('base de données', 'alice'));

        DB::table('note_embeddings')->update(['model' => 'ollama:nomic-embed-text']);

        $this->assertSame([], $this->chercher('base de données', 'alice'));
    }

    /** @return list<int> */
    private function chercherApresIndexation(string $question, string $prenom, int $limit = 5): array
    {
        $this->app->make(NoteIndexer::class)->indexAll();

        return $this->chercher($question, $prenom, $limit);
    }

    /** @return list<int> */
    private function chercher(string $question, string $prenom, int $limit = 5): array
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('La recherche vectorielle demande PostgreSQL avec pgvector.');
        }

        config(['noteflow.search' => 'vector']);
        $recherche = $this->app->make(NoteSearch::class);
        $this->assertInstanceOf(PgvectorNoteSearch::class, $recherche);

        return array_map(fn (NoteHit $hit): int => $hit->noteId, $recherche->search($question, $this->user($prenom), $limit));
    }
}
