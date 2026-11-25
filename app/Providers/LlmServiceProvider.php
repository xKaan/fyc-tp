<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Prompt;
use App\Application\SuggestTags;
use App\Application\SummarizeNote;
use App\Domain\Ports\LlmPort;
use App\Infrastructure\Llm\FakeLlmAdapter;
use App\Infrastructure\Llm\HttpLlmAdapter;
use App\Infrastructure\Llm\OllamaAdapter;
use App\Infrastructure\Prompts\PromptFiles;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Le « configurateur » : seul endroit qui connaît à la fois le port, les
 * adaptateurs et les prompts. Le cœur métier ne demande jamais qu'un LlmPort
 * et un Prompt.
 */
class LlmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LlmPort::class, function (): LlmPort {
            $provider = config('llm.provider');

            return match ($provider) {
                'fake' => new FakeLlmAdapter,
                'http' => new HttpLlmAdapter(
                    (string) config('llm.http.base_url'),
                    config('llm.http.api_key'),
                    (string) config('llm.http.model'),
                    (int) config('llm.http.timeout'),
                ),
                'ollama' => new OllamaAdapter(
                    (string) config('llm.ollama.host'),
                    (string) config('llm.ollama.model'),
                    (int) config('llm.ollama.timeout'),
                    config('llm.ollama.context_length'),
                ),
                default => throw new InvalidArgumentException(
                    "Fournisseur LLM inconnu : [{$provider}]. Valeurs acceptées : fake, http, ollama."
                ),
            };
        });

        $this->app->singleton(PromptFiles::class, fn (): PromptFiles => new PromptFiles(resource_path('prompts')));

        $this->givePrompt(SummarizeNote::class, 'summarize-note');
        $this->givePrompt(SuggestTags::class, 'suggest-tags');
    }

    /** Chaque cas d'usage reçoit son prompt, dans la version choisie en configuration. */
    private function givePrompt(string $useCase, string $name): void
    {
        $this->app->when($useCase)
            ->needs(Prompt::class)
            ->give(fn (): Prompt => $this->app->make(PromptFiles::class)
                ->load($name, (string) config("llm.prompts.{$name}")));
    }
}
