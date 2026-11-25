<?php

declare(strict_types=1);

namespace App\Providers;

use App\Application\Agent\NoteAgent;
use App\Application\Agent\Toolbox;
use App\Application\Agent\Tools\ChercherNotes;
use App\Application\Agent\Tools\CreerTache;
use App\Application\Agent\Tools\ListerTachesOuvertes;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\NoteSearch;
use App\Domain\Ports\ToolCallingPort;
use App\Infrastructure\Prompts\PromptFiles;
use App\Infrastructure\Search\KeywordNoteSearch;
use App\Infrastructure\Search\PgvectorNoteSearch;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Assemble l'agent NoteFlow : le port de tool calling, la recherche, les
 * outils autorisés, le prompt et le plafond d'étapes.
 *
 * Passer à la recherche par similarité : NOTEFLOW_SEARCH=vector dans .env.
 * Passer d'Ollama au modèle en ligne : LLM_PROVIDER dans .env, rien ici.
 */
class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Les trois adaptateurs implémentent les deux ports : le fournisseur
        // de l'agent est celui choisi par LLM_PROVIDER, sans autre réglage.
        $this->app->bind(ToolCallingPort::class, fn (Application $app): ToolCallingPort => $app->make(LlmPort::class));

        $this->app->bind(NoteSearch::class, function (Application $app): NoteSearch {
            $recherche = config('noteflow.search');

            return match ($recherche) {
                'keyword' => $app->make(KeywordNoteSearch::class),
                'vector' => DB::connection()->getDriverName() === 'pgsql'
                    ? $app->make(PgvectorNoteSearch::class)
                    : throw new InvalidArgumentException('La recherche vectorielle demande PostgreSQL avec pgvector (DB_CONNECTION=pgsql).'),
                default => throw new InvalidArgumentException(
                    "Recherche inconnue : [{$recherche}]. Valeurs acceptées : keyword, vector."
                ),
            };
        });

        // La liste blanche des outils : l'agent ne peut demander qu'eux.
        $this->app->bind(Toolbox::class, fn (Application $app): Toolbox => new Toolbox(
            $app->make(ChercherNotes::class),
            $app->make(ListerTachesOuvertes::class),
            $app->make(CreerTache::class),
        ));

        $this->app->bind(NoteAgent::class, fn (Application $app): NoteAgent => new NoteAgent(
            $app->make(ToolCallingPort::class),
            $app->make(PromptFiles::class)->load('note-agent', (string) config('llm.prompts.note-agent')),
            $app->make(Toolbox::class),
            (int) config('noteflow.agent.max_steps'),
        ));
    }
}
