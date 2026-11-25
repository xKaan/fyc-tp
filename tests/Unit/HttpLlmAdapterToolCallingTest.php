<?php

namespace Tests\Unit;

use App\Domain\Ports\AssistantTurn;
use App\Domain\Ports\LlmCallFailed;
use App\Domain\Ports\Message;
use App\Domain\Ports\ToolCall;
use App\Domain\Ports\ToolDefinition;
use App\Infrastructure\Llm\HttpLlmAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Adaptateur fourni : ces tests sont verts dès le départ. */
class HttpLlmAdapterToolCallingTest extends TestCase
{
    private function adaptateur(): HttpLlmAdapter
    {
        return new HttpLlmAdapter('https://api.mistral.ai/v1', 'cle', 'mistral-small-2603', 30);
    }

    private function outil(): ToolDefinition
    {
        return new ToolDefinition('chercher_notes', 'Cherche dans les notes.', [
            'type' => 'object',
            'properties' => ['question' => ['type' => 'string']],
            'required' => ['question'],
        ]);
    }

    public function test_un_appel_d_outil_est_normalise(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response(['choices' => [['message' => [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [['id' => 'abc123XYZ', 'type' => 'function', 'function' => [
                'name' => 'chercher_notes',
                'arguments' => '{"question":"base de données"}',
            ]]],
        ]]]])]);

        $tour = $this->adaptateur()->next([Message::system('Consigne'), Message::user('Question')], [$this->outil()]);

        $this->assertTrue($tour->hasToolCalls());
        $this->assertEquals(new ToolCall('abc123XYZ', 'chercher_notes', ['question' => 'base de données']), $tour->toolCalls[0]);

        Http::assertSent(fn (Request $requete) => $requete['tools'][0]['type'] === 'function'
            && $requete['tools'][0]['function']['name'] === 'chercher_notes'
            && $requete['tools'][0]['function']['parameters']['required'] === ['question']);
    }

    public function test_l_historique_rappelle_l_identifiant_de_l_appel(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response(['choices' => [['message' => ['content' => 'Réponse [note:8]']]]])]);

        $appel = new ToolCall('abc123XYZ', 'chercher_notes', ['question' => 'base']);

        $tour = $this->adaptateur()->next([
            Message::system('Consigne'),
            Message::user('Question'),
            Message::assistantToolCalls(AssistantTurn::toolCalls($appel)),
            Message::toolResult($appel, '{"notes":[]}'),
        ], [$this->outil()]);

        $this->assertSame('Réponse [note:8]', $tour->text);

        Http::assertSent(fn (Request $requete) => $requete['messages'][2]['tool_calls'][0]['id'] === 'abc123XYZ'
            && $requete['messages'][2]['tool_calls'][0]['function']['arguments'] === '{"question":"base"}'
            && $requete['messages'][3] === ['role' => 'tool', 'tool_call_id' => 'abc123XYZ', 'content' => '{"notes":[]}']);
    }

    public function test_des_arguments_illisibles_donnent_un_tableau_vide(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response(['choices' => [['message' => [
            'tool_calls' => [['id' => 'a1', 'function' => ['name' => 'chercher_notes', 'arguments' => '{pas du json']]],
        ]]]])]);

        $tour = $this->adaptateur()->next([Message::user('Question')], [$this->outil()]);

        $this->assertSame([], $tour->toolCalls[0]->arguments);
    }

    public function test_une_reponse_vide_est_une_panne(): void
    {
        Http::fake(['https://api.mistral.ai/*' => Http::response(['choices' => [['message' => ['content' => null]]]])]);

        $this->expectException(LlmCallFailed::class);

        $this->adaptateur()->next([Message::user('Question')], [$this->outil()]);
    }
}
