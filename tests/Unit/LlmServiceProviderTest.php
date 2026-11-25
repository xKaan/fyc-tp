<?php

namespace Tests\Unit;

use App\Application\SummarizeNote;
use App\Domain\Ports\LlmPort;
use App\Infrastructure\Llm\FakeLlmAdapter;
use App\Infrastructure\Llm\HttpLlmAdapter;
use App\Infrastructure\Llm\OllamaAdapter;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * C'est ici, et seulement ici, que l'on vérifie le basculement par la
 * configuration. Les tests de fonctionnalité fournis restent agnostiques.
 */
class LlmServiceProviderTest extends TestCase
{
    public function test_chaque_valeur_de_configuration_resout_son_adaptateur(): void
    {
        foreach (['fake' => FakeLlmAdapter::class, 'http' => HttpLlmAdapter::class, 'ollama' => OllamaAdapter::class] as $provider => $classe) {
            config(['llm.provider' => $provider]);

            $this->assertInstanceOf($classe, $this->app->make(LlmPort::class), "llm.provider = {$provider}");
        }
    }

    public function test_un_fournisseur_inconnu_est_signale_clairement(): void
    {
        config(['llm.provider' => 'fournisseur-imaginaire']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(LlmPort::class);
    }

    public function test_le_cas_d_usage_recoit_le_prompt_dans_la_version_configuree(): void
    {
        config(['llm.provider' => 'fake', 'llm.prompts.summarize-note' => 'v1']);

        $this->assertInstanceOf(SummarizeNote::class, $this->app->make(SummarizeNote::class));
    }

    public function test_une_version_de_prompt_inexistante_est_signalee_clairement(): void
    {
        config(['llm.provider' => 'fake', 'llm.prompts.summarize-note' => 'v99']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(SummarizeNote::class);
    }
}
