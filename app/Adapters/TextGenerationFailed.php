<?php

declare(strict_types=1);

namespace App\Adapters;

use RuntimeException;
use Throwable;

/**
 * Panne du service de generation de texte, quelle qu'en soit la cause.
 *
 * Le code metier n'a pas a savoir s'il s'agit d'un timeout, d'un quota
 * depasse ou d'un JSON inattendu : une seule exception suffit a decider quoi
 * faire, et elle ne laisse fuir aucun detail du fournisseur.
 */
final class TextGenerationFailed extends RuntimeException
{
    public static function unreachable(string $provider, ?Throwable $previous = null): self
    {
        return new self("Le fournisseur [{$provider}] est injoignable.", 0, $previous);
    }

    public static function rejected(string $provider, int $status): self
    {
        return new self("Le fournisseur [{$provider}] a repondu une erreur HTTP {$status}.");
    }

    public static function unexpectedPayload(string $provider): self
    {
        return new self("La reponse du fournisseur [{$provider}] n'a pas la forme attendue.");
    }
}
