<?php

namespace Tests\Unit;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_tags_ecrits_sont_normalises_et_dedoublonnes(): void
    {
        $note = new Note;

        $note->tags = ' Projet , #IDEES ,, projet ';

        $this->assertSame('projet,idees', $note->tags);
    }

    public function test_les_tags_acceptent_aussi_un_tableau(): void
    {
        $note = new Note;

        $note->tags = ['Courses', 'Maison'];

        $this->assertSame('courses,maison', $note->tags);
    }

    public function test_une_liste_de_tags_vide_est_stockee_a_null(): void
    {
        $note = new Note;

        $note->tags = '  ,  ';

        $this->assertNull($note->tags);
    }

    public function test_la_liste_de_tags_se_relit_sous_forme_de_tableau(): void
    {
        $note = new Note;
        $note->tags = 'courses,maison';

        $this->assertSame(['courses', 'maison'], $note->tag_list);
    }

    public function test_une_note_sans_tag_a_une_liste_vide(): void
    {
        $this->assertSame([], (new Note)->tag_list);
    }

    public function test_l_apercu_est_tronque(): void
    {
        $note = new Note(['content' => str_repeat('a', 200)]);

        $this->assertSame(str_repeat('a', 120).'...', $note->excerpt);
    }

    public function test_le_temps_de_lecture_est_estime_a_partir_du_nombre_de_mots(): void
    {
        $note = new Note(['content' => trim(str_repeat('mot ', 450))]);

        $this->assertSame(450, $note->word_count);
        $this->assertSame(3, $note->reading_time);
    }

    public function test_une_note_courte_vaut_au_moins_une_minute_de_lecture(): void
    {
        $this->assertSame(1, (new Note(['content' => 'trois petits mots']))->reading_time);
    }

    public function test_le_filtrage_par_tag_se_fait_en_base(): void
    {
        Note::create(['title' => 'Courses', 'content' => 'Du pain', 'tags' => 'courses,maison']);
        Note::create(['title' => 'Reunion', 'content' => 'Le support', 'tags' => 'travail']);
        // Piege : le tag "cours" ne doit pas ressortir sur une recherche de
        // "courses", et inversement un tag ne doit pas matcher par sous-chaine.
        Note::create(['title' => 'Cours', 'content' => 'Le poly', 'tags' => 'cours']);

        $titres = Note::tagged('Courses')->pluck('title')->all();

        $this->assertSame(['Courses'], $titres);
    }

    public function test_la_recherche_par_titre_se_fait_en_base(): void
    {
        Note::create(['title' => 'Courses', 'content' => 'Du pain']);
        Note::create(['title' => 'Reunion', 'content' => 'Le support']);

        $this->assertSame(['Courses'], Note::matchingTitle('cour')->pluck('title')->all());
    }

    public function test_l_identifiant_n_est_pas_remplissable_en_masse(): void
    {
        $note = Note::create([
            'id' => 1234,
            'title' => 'Titre',
            'content' => 'Contenu',
        ]);

        // Avec $guarded = [], cette note se serait vu imposer l'id 1234.
        $this->assertNotSame(1234, $note->id);
    }
}
