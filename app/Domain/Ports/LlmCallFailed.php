<?php

declare(strict_types=1);

namespace App\Domain\Ports;

use RuntimeException;
use Throwable;

/**
 * Panne de l'appel au modèle, quelle qu'en soit la cause.
 *
 * Elle appartient au port, pas aux adaptateurs : le cœur métier l'attrape sans
 * rien savoir du fournisseur. Timeout, quota dépassé ou JSON inattendu : une
 * seule exception suffit à décider quoi montrer à l'utilisateur, et aucun
 * détail du fournisseur ne fuit dans le contrôleur.
 */
final class LlmCallFailed extends RuntimeException
{
    public static function unreachable(string $provider, ?Throwable $previous = null): self
    {
        return new self("Le fournisseur [{$provider}] est injoignable.", 0, $previous);
    }

    public static function rejected(string $provider, int $status): self
    {
        return new self("Le fournisseur [{$provider}] a répondu une erreur HTTP {$status}.");
    }

    public static function unexpectedPayload(string $provider): self
    {
        return new self("La réponse du fournisseur [{$provider}] n'a pas la forme attendue.");
    }
}
