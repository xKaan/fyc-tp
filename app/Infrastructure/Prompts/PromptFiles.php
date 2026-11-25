<?php

declare(strict_types=1);

namespace App\Infrastructure\Prompts;

use App\Application\Prompt;
use InvalidArgumentException;

/**
 * Lit les prompts rangés dans resources/prompts/<nom>/<version>.md.
 *
 * Un prompt est du code : il vit dans Git, chaque changement de fond donne une
 * nouvelle version, et la version servie se choisit dans config/llm.php.
 */
final class PromptFiles
{
    public function __construct(private readonly string $directory) {}

    public function load(string $name, string $version): Prompt
    {
        $chemin = rtrim($this->directory, '/')."/{$name}/{$version}.md";

        if (! is_file($chemin)) {
            throw new InvalidArgumentException("Prompt introuvable : [{$name}] en version [{$version}] ({$chemin}).");
        }

        return new Prompt($name, $version, trim((string) file_get_contents($chemin)));
    }
}
