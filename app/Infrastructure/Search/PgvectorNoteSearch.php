<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Ports\EmbeddingPort;
use App\Domain\Ports\NoteHit;
use App\Domain\Ports\NoteSearch;
use App\Infrastructure\Embeddings\Vectors;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recherche par similarité sur pgvector : la question est vectorisée par le
 * même modèle que les notes, puis on prend les notes les plus proches.
 *
 * Écrite en SQL, sans SDK : `<=>` est la distance cosinus de pgvector (0 pour
 * deux vecteurs de même direction, 2 pour deux vecteurs opposés). Le score
 * renvoyé est la similarité, 1 - distance.
 *
 * Les droits sont dans le WHERE, comme pour la recherche par mots-clés : une
 * note d'un projet que l'utilisateur ne lit pas n'est jamais comparée.
 *
 * Une recherche par similarité renvoie toujours les notes « les moins
 * éloignées », même pour une question sans rapport (« la capitale de
 * l'Australie »). C'est la boucle d'agent qui refuse une réponse sans
 * source valable : la recherche ne sait pas dire « rien ».
 */
final class PgvectorNoteSearch implements NoteSearch
{
    private const EXCERPT_LENGTH = 400;

    public function __construct(private readonly EmbeddingPort $embeddings) {}

    public function search(string $question, User $user, int $limit = 5): array
    {
        $vecteur = Vectors::literal($this->embeddings->embed([$question])[0]);

        $lignes = DB::table('note_embeddings')
            ->join('notes', 'notes.id', '=', 'note_embeddings.note_id')
            ->join('projects', 'projects.id', '=', 'notes.project_id')
            ->whereIn('notes.project_id', $user->readableProjectIds())
            ->where('note_embeddings.model', $this->embeddings->modelName())
            ->select('notes.id', 'notes.title', 'notes.content', 'projects.name as project')
            ->selectRaw('note_embeddings.embedding <=> ?::vector as distance', [$vecteur])
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        return $lignes->map(fn (object $ligne): NoteHit => new NoteHit(
            (int) $ligne->id,
            $ligne->title,
            $ligne->project,
            Str::limit($ligne->content, self::EXCERPT_LENGTH),
            round(1 - (float) $ligne->distance, 4),
        ))->all();
    }
}
