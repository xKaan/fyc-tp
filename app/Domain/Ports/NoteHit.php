<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/** Une note trouvée par la recherche, avec ce qu'il faut pour la citer. */
final class NoteHit
{
    public function __construct(
        public readonly int $noteId,
        public readonly string $title,
        public readonly string $project,
        public readonly string $excerpt,
        public readonly float $score,
    ) {}
}
