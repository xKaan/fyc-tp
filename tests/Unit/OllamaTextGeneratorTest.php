<?php

namespace Tests\Unit;

use App\Adapters\OllamaTextGenerator;
use App\Adapters\TextGenerationFailed;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaTextGeneratorTest extends TestCase
{
    private function generateur(): OllamaTextGenerator
    {
        return new OllamaTextGenerator('http://host.docker.internal:11434', 'llama3.2:3b', 120);
    }

    public function test_l_instruction_part_dans_le_champ_system_et_le_contenu_dans_le_prompt(): void
    {
        Http::fake([
            'http://host.docker.internal:11434/*' => Http::response(['response' => '  un resume  ']),
        ]);

        $resultat = $this->generateur()->generate('Resume ceci.', 'Le contenu de la note.');

        $this->assertSame('un resume', $resultat);

        Http::assertSent(function (Request $requete) {
            return $requete->url() === 'http://host.docker.internal:11434/api/generate'
                && $requete['model'] === 'llama3.2:3b'
                && $requete['system'] === 'Resume ceci.'
                && $requete['prompt'] === 'Le contenu de la note.'
                && $requete['stream'] === false;
        });
    }

    public function test_une_erreur_http_devient_une_exception_metier(): void
    {
        Http::fake(['http://host.docker.internal:11434/*' => Http::response('', 500)]);

        $this->expectException(TextGenerationFailed::class);

        $this->generateur()->generate('Resume ceci.', 'Le contenu.');
    }

    public function test_une_reponse_de_forme_inattendue_devient_une_exception_metier(): void
    {
        Http::fake(['http://host.docker.internal:11434/*' => Http::response(['bidule' => 'machin'])]);

        $this->expectException(TextGenerationFailed::class);

        $this->generateur()->generate('Resume ceci.', 'Le contenu.');
    }
}
