<?php

declare(strict_types=1);

namespace App\Application;

/**
 * Une consigne versionnée. Le nom et la version voyagent avec le texte : en
 * séance 3, ils iront dans chaque trace pour savoir quelle version du prompt
 * a produit quelle réponse.
 */
final class Prompt
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
        public readonly string $text,
    ) {}
}
