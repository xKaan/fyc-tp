<?php

declare(strict_types=1);

namespace App\Providers;

use App\Adapters\MistralTextGenerator;
use App\Adapters\OllamaTextGenerator;
use App\Ports\TextGenerator;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Le seul endroit du projet ou l'on decide quelle implementation du port est
 * utilisee. Le code metier, lui, ne demande jamais qu'un TextGenerator.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TextGenerator::class, function (): TextGenerator {
            $provider = config('ai.provider');

            return match ($provider) {
                'mistral' => new MistralTextGenerator(
                    (string) config('ai.mistral.url'),
                    (string) config('ai.mistral.key'),
                    (string) config('ai.mistral.model'),
                    (int) config('ai.mistral.timeout'),
                ),
                'ollama' => new OllamaTextGenerator(
                    (string) config('ai.ollama.url'),
                    (string) config('ai.ollama.model'),
                    (int) config('ai.ollama.timeout'),
                ),
                default => throw new InvalidArgumentException(
                    "Fournisseur d'IA inconnu : [{$provider}]. Valeurs acceptees : mistral, ollama."
                ),
            };
        });
    }
}
