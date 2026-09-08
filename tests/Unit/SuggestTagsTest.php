<?php

namespace Tests\Unit;

use App\Adapters\FakeTextGenerator;
use App\Models\Note;
use App\UseCases\SuggestTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestTagsTest extends TestCase
{
    use RefreshDatabase;

    private function note(): Note
    {
        return Note::create(['title' => 'Courses', 'content' => 'Acheter du pain.']);
    }

    public function test_il_decoupe_et_normalise_la_reponse_du_modele(): void
    {
        (new SuggestTags(new FakeTextGenerator('Courses, #Maison , ALIMENTATION')))($this->note());

        $this->assertSame('courses,maison,alimentation', Note::first()->tags);
    }

    public function test_il_ne_garde_que_les_cinq_premiers_tags(): void
    {
        (new SuggestTags(new FakeTextGenerator('un, deux, trois, quatre, cinq, six, sept')))($this->note());

        $this->assertSame('un,deux,trois,quatre,cinq', Note::first()->tags);
    }

    public function test_le_contenu_utilisateur_n_est_pas_concatene_a_l_instruction(): void
    {
        $generateur = new FakeTextGenerator('courses');

        (new SuggestTags($generateur))($this->note());

        $appel = $generateur->calls[0];

        $this->assertStringNotContainsString('Acheter du pain', $appel['instruction']);
        $this->assertStringContainsString('Acheter du pain', $appel['input']);
    }
}
