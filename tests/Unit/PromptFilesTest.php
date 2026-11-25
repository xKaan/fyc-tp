<?php

namespace Tests\Unit;

use App\Infrastructure\Prompts\PromptFiles;
use InvalidArgumentException;
use Tests\TestCase;

class PromptFilesTest extends TestCase
{
    public function test_il_charge_un_prompt_versionne_avec_son_nom_et_sa_version(): void
    {
        $prompt = (new PromptFiles(resource_path('prompts')))->load('summarize-note', 'v1');

        $this->assertSame('summarize-note', $prompt->name);
        $this->assertSame('v1', $prompt->version);
        $this->assertStringContainsString('3 phrases maximum', $prompt->text);
    }

    public function test_chaque_prompt_versionne_existe_dans_la_version_configuree(): void
    {
        $prompts = new PromptFiles(resource_path('prompts'));

        foreach (config('llm.prompts') as $nom => $version) {
            $this->assertNotSame('', $prompts->load($nom, $version)->text, "{$nom} {$version}");
        }
    }

    public function test_un_prompt_absent_est_signale_clairement(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PromptFiles(resource_path('prompts')))->load('summarize-note', 'v99');
    }
}
