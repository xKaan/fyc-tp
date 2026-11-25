<?php

namespace Tests\Feature;

use App\Models\Note;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ces tests sont volontairement agnostiques du fournisseur d'IA : les deux
 * services sont simules et renvoient le meme texte, et les assertions ne
 * portent que sur le resultat visible par l'utilisateur.
 *
 * Consequence : peu importe que l'application choisisse Mistral ou Ollama, et
 * peu importe l'endroit du code ou ce choix est fait. Ces tests restent donc
 * valables apres reorganisation.
 */
class NoteAiTest extends TestCase
{
    use RefreshDatabase;

    private function simulerIa(string $texte): void
    {
        Http::fake([
            'https://api.mistral.ai/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => $texte]],
                ],
            ]),
            'http://host.docker.internal:11434/*' => Http::response([
                'message' => ['role' => 'assistant', 'content' => $texte],
            ]),
        ]);
    }

    public function test_le_resume_est_enregistre_sur_la_note(): void
    {
        $this->simulerIa('Une note qui parle de courses.');

        $note = Note::create([
            'title' => 'Courses',
            'content' => 'Acheter du pain, du lait et des oeufs.',
        ]);

        $reponse = $this->post('/notes/' . $note->id . '/summarize');

        $reponse->assertRedirect();
        $this->assertSame('Une note qui parle de courses.', $note->fresh()->summary);
    }

    public function test_le_resume_est_visible_sur_la_page_de_la_note(): void
    {
        $this->simulerIa('Une note qui parle de courses.');

        $note = Note::create([
            'title' => 'Courses',
            'content' => 'Acheter du pain, du lait et des oeufs.',
        ]);

        $this->post('/notes/' . $note->id . '/summarize');

        $this->get('/notes/' . $note->id)->assertSee('Une note qui parle de courses.');
    }

    public function test_les_tags_suggeres_sont_enregistres(): void
    {
        $this->simulerIa('Courses, Maison, Alimentation');

        $note = Note::create([
            'title' => 'Courses',
            'content' => 'Acheter du pain, du lait et des oeufs.',
        ]);

        $reponse = $this->post('/notes/' . $note->id . '/tags');

        $reponse->assertRedirect();
        $this->assertSame('courses,maison,alimentation', $note->fresh()->tags);
    }

    public function test_les_tags_sont_limites_a_cinq(): void
    {
        $this->simulerIa('un, deux, trois, quatre, cinq, six, sept');

        $note = Note::create([
            'title' => 'Beaucoup de tags',
            'content' => 'Un contenu quelconque.',
        ]);

        $this->post('/notes/' . $note->id . '/tags');

        $this->assertCount(5, explode(',', $note->fresh()->tags));
    }
}
