<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Ports\NoteHit;
use App\Domain\Ports\NoteSearch;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Recherche par mots-clés, fournie pour que le TP tienne sans pgvector.
 *
 * Elle découpe la question en mots, retire les mots vides, tronque chaque mot
 * à 6 lettres (une racinisation grossière : « décidé » trouve « décision »),
 * cherche les notes qui contiennent au moins un mot, puis classe par nombre
 * de mots trouvés, avec un bonus pour le titre.
 *
 * C'est volontairement simple : la recherche plein texte de PostgreSQL et la
 * recherche vectorielle de S2.3 font mieux, et se branchent sur le même port.
 */
final class KeywordNoteSearch implements NoteSearch
{
    private const STOP_WORDS = [
        'les', 'des', 'une', 'pour', 'par', 'sur', 'dans', 'avec', 'que', 'qui', 'quoi', 'est', 'sont',
        'ont', 'été', 'cette', 'ces', 'son', 'ses', 'leur', 'nous', 'vous', 'ils', 'elle', 'elles',
        'pas', 'mais', 'aux', 'comment', 'pourquoi', 'quand', 'quel', 'quelle', 'quels', 'quelles',
        'tout', 'tous', 'fait', 'faire', 'notre', 'nos', 'votre', 'vos', 'note', 'notes',
    ];

    private const CANDIDATES = 50;

    private const EXCERPT_LENGTH = 400;

    public function search(string $question, User $user, int $limit = 5): array
    {
        $termes = self::terms($question);

        if ($termes === []) {
            return [];
        }

        $candidates = Note::query()
            ->with('project')
            // TODO étape 2 : filtrer par droits ici, dans la requête.
            // Seules les notes des projets de $user doivent être lues, et une
            // note sans projet ne doit jamais sortir.
            ->where(function (Builder $query) use ($termes): void {
                foreach ($termes as $terme) {
                    $query->orWhereLike('title', "%{$terme}%")->orWhereLike('content', "%{$terme}%");
                }
            })
            ->limit(self::CANDIDATES)
            ->get();

        return $candidates
            ->map(fn (Note $note): NoteHit => new NoteHit(
                $note->id,
                $note->title,
                $note->project->name,
                Str::limit($note->content, self::EXCERPT_LENGTH),
                self::score($note, $termes),
            ))
            ->sortByDesc(fn (NoteHit $hit): float => $hit->score)
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return list<string> */
    public static function terms(string $question): array
    {
        $mots = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $termes = [];

        foreach ($mots as $mot) {
            if (mb_strlen($mot) < 3 || in_array($mot, self::STOP_WORDS, true)) {
                continue;
            }

            $termes[] = mb_substr($mot, 0, 6);
        }

        return array_values(array_unique($termes));
    }

    /** @param  list<string>  $termes */
    private static function score(Note $note, array $termes): float
    {
        $titre = mb_strtolower($note->title);
        $contenu = mb_strtolower($note->content);
        $score = 0.0;

        foreach ($termes as $terme) {
            if (str_contains($titre, $terme)) {
                $score += 2;
            }

            if (str_contains($contenu, $terme)) {
                $score += 1;
            }
        }

        return $score;
    }
}
