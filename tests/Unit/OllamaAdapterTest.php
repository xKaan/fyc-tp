<?php

namespace Tests\Unit;

use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\Message;
use App\Infrastructure\Llm\OllamaAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaAdapterTest extends TestCase
{
    private function messages(): array
    {
        return [Message::system('Résume ceci.'), Message::user('Le contenu de la note.')];
    }

    public function test_il_traduit_les_messages_pour_l_api_chat_d_ollama(): void
    {
        Http::fake([
            'http://host.docker.internal:11434/*' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '  un résumé  '],
            ]),
        ]);

        $adaptateur = new OllamaAdapter('http://host.docker.internal:11434', 'llama3.2:3b', 120);

        $this->assertSame('un résumé', $adaptateur->complete($this->messages()));

        Http::assertSent(fn (Request $requete) => $requete->url() === 'http://host.docker.internal:11434/api/chat'
            && $requete['model'] === 'llama3.2:3b'
            && $requete['stream'] === false
            && $requete['messages'][0] === ['role' => 'system', 'content' => 'Résume ceci.']
            && $requete['messages'][1] === ['role' => 'user', 'content' => 'Le contenu de la note.']
            && ! isset($requete['options']));
    }

    public function test_la_taille_de_contexte_configuree_part_dans_num_ctx(): void
    {
        Http::fake(['*' => Http::response(['message' => ['content' => 'ok']])]);

        (new OllamaAdapter('http://host.docker.internal:11434', 'llama3.2:3b', 120, 8192))
            ->complete($this->messages());

        Http::assertSent(fn (Request $requete) => $requete['options'] === ['num_ctx' => 8192]);
    }

    public function test_une_erreur_http_devient_une_exception_du_port(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(LlmCallFailed::class);

        (new OllamaAdapter('http://host.docker.internal:11434', 'llama3.2:3b', 120))->complete($this->messages());
    }

    public function test_une_reponse_de_forme_inattendue_devient_une_exception_du_port(): void
    {
        // Forme de l'ancienne API /api/generate : l'adaptateur ne doit pas la confondre.
        Http::fake(['*' => Http::response(['response' => 'texte'])]);

        $this->expectException(LlmCallFailed::class);

        (new OllamaAdapter('http://host.docker.internal:11434', 'llama3.2:3b', 120))->complete($this->messages());
    }
}
