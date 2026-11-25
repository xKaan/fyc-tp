<?php

namespace Tests\Unit;

use App\Application\Prompt;
use App\Application\SuggestTags;
use App\Domain\Ports\Role;
use App\Infrastructure\Llm\FakeLlmAdapter;
use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestTagsTest extends TestCase
{
    use RefreshDatabase;

    private function suggerer(FakeLlmAdapter $llm): void
    {
        $note = Note::create(['title' => 'Courses', 'content' => 'Acheter du pain.']);

        (new SuggestTags($llm, new Prompt('suggest-tags', 'test', 'Propose des tags.')))($note);
    }

    public function test_il_decoupe_et_normalise_la_reponse_du_modele(): void
    {
        $this->suggerer(new FakeLlmAdapter('Courses, #Maison , ALIMENTATION'));

        $this->assertSame('courses,maison,alimentation', Note::first()->tags);
    }

    public function test_le_code_garantit_le_plafond_de_cinq_tags_meme_si_le_modele_l_ignore(): void
    {
        $this->suggerer(new FakeLlmAdapter('un, deux, trois, quatre, cinq, six, sept'));

        $this->assertSame('un,deux,trois,quatre,cinq', Note::first()->tags);
    }

    public function test_le_contenu_de_la_note_part_dans_le_message_utilisateur(): void
    {
        $llm = new FakeLlmAdapter('courses');

        $this->suggerer($llm);

        [$consigne, $contenu] = $llm->calls[0];

        $this->assertSame(Role::System, $consigne->role);
        $this->assertStringNotContainsString('Acheter du pain', $consigne->content);
        $this->assertSame(Role::User, $contenu->role);
        $this->assertStringContainsString('Acheter du pain', $contenu->content);
    }
}
