<?php

declare(strict_types=1);

namespace App\Infrastructure\Search;

use App\Domain\Ports\EmbeddingPort;
use App\Infrastructure\Embeddings\Vectors;
use App\Models\Note;
use Illuminate\Support\Facades\DB;

/**
 * Indexation des notes pour la recherche par similarité : la partie
 * « données » du pipeline de S2.3.
 *
 * Chaque note devient un texte (titre puis contenu), vectorisé par le modèle
 * configuré. Une note déjà indexée avec le même modèle et le même texte n'est
 * pas recalculée : relancer l'indexation ne coûte que les notes modifiées.
 */
final class NoteIndexer
{
    /** Nombre de textes par appel au fournisseur. */
    private const BATCH = 8;

    public function __construct(private readonly EmbeddingPort $embeddings) {}

    /** @return array{indexed: int, skipped: int} */
    public function indexAll(): array
    {
        $modele = $this->embeddings->modelName();
        $deja = DB::table('note_embeddings')->where('model', $modele)->pluck('content_hash', 'note_id');

        $aFaire = Note::query()->whereNotNull('project_id')->orderBy('id')->get()
            ->filter(fn (Note $note): bool => ($deja[$note->id] ?? null) !== self::hash($note));

        foreach ($aFaire->chunk(self::BATCH) as $lot) {
            $vecteurs = $this->embeddings->embed($lot->map(fn (Note $note): string => self::text($note))->values()->all());

            foreach ($lot->values() as $rang => $note) {
                DB::table('note_embeddings')->updateOrInsert(
                    ['note_id' => $note->id],
                    [
                        'model' => $modele,
                        'content_hash' => self::hash($note),
                        'embedding' => Vectors::literal($vecteurs[$rang]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        return ['indexed' => $aFaire->count(), 'skipped' => Note::whereNotNull('project_id')->count() - $aFaire->count()];
    }

    /** Le texte vectorisé : le titre porte souvent le sens, on le garde en tête. */
    public static function text(Note $note): string
    {
        return $note->title."\n\n".$note->content;
    }

    private static function hash(Note $note): string
    {
        return hash('sha256', self::text($note));
    }
}
