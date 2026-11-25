<?php

declare(strict_types=1);

namespace App\Infrastructure\Embeddings;

use App\Domain\Ports\LlmCallFailed;

/** Vérifie ce que renvoie un fournisseur, et écrit un vecteur au format pgvector. */
final class Vectors
{
    /** @return list<list<float>> */
    public static function check(mixed $vecteurs, int $attendus, string $provider): array
    {
        if (! is_array($vecteurs) || count($vecteurs) !== $attendus) {
            throw LlmCallFailed::unexpectedPayload($provider);
        }

        return array_map(function (mixed $vecteur) use ($provider): array {
            if (! is_array($vecteur) || $vecteur === [] || array_filter($vecteur, fn ($x): bool => ! is_int($x) && ! is_float($x)) !== []) {
                throw LlmCallFailed::unexpectedPayload($provider);
            }

            return array_map(fn (int|float $x): float => (float) $x, array_values($vecteur));
        }, array_values($vecteurs));
    }

    /**
     * Le format texte de pgvector, « [0.1,0.2,...] », qui est aussi un tableau
     * JSON valide : la même colonne se relit sous SQLite dans les tests.
     *
     * @param  list<float>  $vecteur
     */
    public static function literal(array $vecteur): string
    {
        return '['.implode(',', array_map(fn (float $x): string => rtrim(rtrim(sprintf('%.8F', $x), '0'), '.') ?: '0', $vecteur)).']';
    }
}
