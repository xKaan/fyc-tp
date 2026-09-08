<?php

namespace Tests\Unit;

use App\Adapters\MistralTextGenerator;
use App\Adapters\TextGenerationFailed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MistralTextGeneratorTest extends TestCase
{
    private function generateur(): MistralTextGenerator
    {
        return new MistralTextGenerator(
            'https://api.mistral.ai/v1/chat/completions',
            'cle-de-test',
            'mistral-small-latest',
            30,
        );
    }

    public function test_l_instruction_et_le_contenu_partent_dans_deux_messages_distincts(): void
    {
        Http::fake([
            'https://api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['content' => '  un resume  ']]],
            ]),
        ]);

        $resultat = $this->generateur()->generate('Resume ceci.', 'Le contenu de la note.');

        $this->assertSame('un resume', $resultat);

        Http::assertSent(function (Request $requete) {
            return $requete->url() === 'https://api.mistral.ai/v1/chat/completions'
                && $requete->hasHeader('Authorization', 'Bearer cle-de-test')
                && $requete['model'] === 'mistral-small-latest'
                && $requete['messages'][0] === ['role' => 'system', 'content' => 'Resume ceci.']
                && $requete['messages'][1] === ['role' => 'user', 'content' => 'Le contenu de la note.'];
        });
    }

    public function test_une_erreur_http_devient_une_exception_metier(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response('', 500)]);

        $this->expectException(TextGenerationFailed::class);

        $this->generateur()->generate('Resume ceci.', 'Le contenu.');
    }

    public function test_une_reponse_de_forme_inattendue_devient_une_exception_metier(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response(['bidule' => 'machin'])]);

        $this->expectException(TextGenerationFailed::class);

        $this->generateur()->generate('Resume ceci.', 'Le contenu.');
    }
}
