<?php

namespace Tests\Unit;

use App\Adapters\MistralTextGenerator;
use App\Adapters\OllamaTextGenerator;
use App\Ports\TextGenerator;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * C'est ici, et seulement ici, que le basculement de fournisseur est verifie.
 * Les tests de fonctionnalite fournis restent volontairement agnostiques.
 */
class AiServiceProviderTest extends TestCase
{
    public function test_la_configuration_mistral_resout_l_adapter_mistral(): void
    {
        config(['ai.provider' => 'mistral']);

        $this->assertInstanceOf(MistralTextGenerator::class, $this->app->make(TextGenerator::class));
    }

    public function test_la_configuration_ollama_resout_l_adapter_ollama(): void
    {
        config(['ai.provider' => 'ollama']);

        $this->assertInstanceOf(OllamaTextGenerator::class, $this->app->make(TextGenerator::class));
    }

    public function test_un_fournisseur_inconnu_est_signale_clairement(): void
    {
        config(['ai.provider' => 'fournisseur-imaginaire']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(TextGenerator::class);
    }
}
