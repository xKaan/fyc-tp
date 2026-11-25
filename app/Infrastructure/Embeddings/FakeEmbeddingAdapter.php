<?php

declare(strict_types=1);

namespace App\Infrastructure\Embeddings;

use App\Domain\Ports\EmbeddingPort;
use App\Infrastructure\Search\KeywordNoteSearch;

/**
 * Doublure de vectorisation : aucune requête réseau, et des vecteurs
 * déterministes.
 *
 * Chaque mot (racinisé comme dans KeywordNoteSearch) ajoute 1 à une
 * coordonnée choisie par un hachage du mot, puis le vecteur est normalisé.
 * Deux textes qui partagent des mots sont donc proches : assez pour tester
 * l'indexation et la recherche sans modèle, jamais pour mesurer un recall.
 */
final class FakeEmbeddingAdapter implements EmbeddingPort
{
    public function __construct(private readonly int $dimensions = 768) {}

    public function embed(array $texts): array
    {
        return array_map(fn (string $texte): array => $this->vector($texte), array_values($texts));
    }

    public function modelName(): string
    {
        return 'fake:hash-'.$this->dimensions;
    }

    /** @return list<float> */
    private function vector(string $texte): array
    {
        $vecteur = array_fill(0, $this->dimensions, 0.0);

        foreach (KeywordNoteSearch::terms($texte) as $terme) {
            $vecteur[crc32($terme) % $this->dimensions] += 1.0;
        }

        $norme = sqrt(array_sum(array_map(fn (float $x): float => $x * $x, $vecteur)));

        // Un texte sans mot utile : un vecteur fixe plutôt qu'un vecteur nul,
        // dont la distance cosinus n'est pas définie.
        if ($norme === 0.0) {
            $vecteur[0] = 1.0;

            return $vecteur;
        }

        return array_map(fn (float $x): float => $x / $norme, $vecteur);
    }
}
