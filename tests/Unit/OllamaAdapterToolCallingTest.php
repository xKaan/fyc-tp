<?php

namespace Tests\Unit;

use App\Domain\Ports\AssistantTurn;
use App\Domain\Ports\Message;
use App\Domain\Ports\ToolCall;
use App\Domain\Ports\ToolDefinition;
use App\Infrastructure\Llm\OllamaAdapter;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Adaptateur fourni : ces tests sont verts dès le départ. */
class OllamaAdapterToolCallingTest extends TestCase
{
    private function adaptateur(): OllamaAdapter
    {
        return new OllamaAdapter('http://host.docker.internal:11434', 'llama3.2:3b', 120);
    }

    private function outil(): ToolDefinition
    {
        return new ToolDefinition('chercher_notes', 'Cherche dans les notes.', ['type' => 'object', 'properties' => []]);
    }

    public function test_un_appel_sans_identifiant_en_recoit_un(): void
    {
        Http::fake(['http://host.docker.internal:11434/*' => Http::response(['message' => [
            'role' => 'assistant',
            'content' => '',
            'tool_calls' => [['function' => ['name' => 'chercher_notes', 'arguments' => ['question' => 'base de données']]]],
        ]])]);

        $tour = $this->adaptateur()->next([Message::user('Question')], [$this->outil()]);

        $this->assertSame('ollama-1', $tour->toolCalls[0]->id);
        $this->assertSame(['question' => 'base de données'], $tour->toolCalls[0]->arguments);

        Http::assertSent(fn (Request $requete) => $requete['stream'] === false
            && $requete['tools'][0]['function']['name'] === 'chercher_notes');
    }

    public function test_le_resultat_d_outil_repart_avec_tool_name(): void
    {
        Http::fake(['http://host.docker.internal:11434/*' => Http::response(['message' => ['content' => 'Je ne sais pas.']])]);

        $tour = $this->adaptateur()->next([Message::user('Question')], [$this->outil()]);
        $this->assertSame('Je ne sais pas.', $tour->text);

        $appel = new ToolCall('ollama-1', 'chercher_notes', ['question' => 'base']);

        $this->adaptateur()->next([
            Message::user('Question'),
            Message::assistantToolCalls(AssistantTurn::toolCalls($appel)),
            Message::toolResult($appel, '{"notes":[]}'),
        ], [$this->outil()]);

        Http::assertSent(fn (Request $requete) => isset($requete['messages'][2])
            && $requete['messages'][1]['tool_calls'][0]['function'] === ['name' => 'chercher_notes', 'arguments' => ['question' => 'base']]
            && $requete['messages'][2] === ['role' => 'tool', 'tool_name' => 'chercher_notes', 'content' => '{"notes":[]}']);
    }

    public function test_des_arguments_en_chaine_sont_decodes(): void
    {
        Http::fake(['http://host.docker.internal:11434/*' => Http::response(['message' => [
            'tool_calls' => [['function' => ['name' => 'chercher_notes', 'arguments' => '{"question":"tâches"}']]],
        ]])]);

        $tour = $this->adaptateur()->next([Message::user('Question')], [$this->outil()]);

        $this->assertSame(['question' => 'tâches'], $tour->toolCalls[0]->arguments);
    }
}
