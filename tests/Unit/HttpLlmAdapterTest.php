<?php

namespace Tests\Unit;

use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\Message;
use App\Infrastructure\Llm\HttpLlmAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le seul endroit où l'on vérifie le format JSON du fournisseur en ligne :
 * c'est le rôle de l'adaptateur, et de lui seul.
 */
class HttpLlmAdapterTest extends TestCase
{
    private function adaptateur(?string $cle = 'cle-de-test'): HttpLlmAdapter
    {
        return new HttpLlmAdapter('https://api.mistral.ai/v1', $cle, 'mistral-small-2603', 30);
    }

    private function messages(): array
    {
        return [Message::system('Résume ceci.'), Message::user('Le contenu de la note.')];
    }

    public function test_il_traduit_les_messages_au_format_chat_completions(): void
    {
        Http::fake([
            'https://api.mistral.ai/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '  un résumé  ']]],
            ]),
        ]);

        $this->assertSame('un résumé', $this->adaptateur()->complete($this->messages()));

        Http::assertSent(fn (Request $requete) => $requete->url() === 'https://api.mistral.ai/v1/chat/completions'
            && $requete->hasHeader('Authorization', 'Bearer cle-de-test')
            && $requete['model'] === 'mistral-small-2603'
            && $requete['messages'] === [
                ['role' => 'system', 'content' => 'Résume ceci.'],
                ['role' => 'user', 'content' => 'Le contenu de la note.'],
            ]);
    }

    public function test_sans_cle_il_n_envoie_pas_d_en_tete_authorization(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $this->adaptateur(null)->complete($this->messages());

        Http::assertSent(fn (Request $requete) => ! $requete->hasHeader('Authorization'));
    }

    public function test_une_erreur_http_devient_une_exception_du_port(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response('', 500)]);

        $this->expectException(LlmCallFailed::class);

        $this->adaptateur()->complete($this->messages());
    }

    public function test_une_reponse_de_forme_inattendue_devient_une_exception_du_port(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response(['bidule' => 'machin'])]);

        $this->expectException(LlmCallFailed::class);

        $this->adaptateur()->complete($this->messages());
    }
}
