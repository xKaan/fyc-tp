<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Le contrat d'un outil tel que le modèle le voit (S2.1) : un nom, une
 * description, et le schéma JSON de ses arguments.
 *
 * La description et le schéma sont lus par le modèle : ce sont eux qui lui
 * font choisir le bon outil avec les bons arguments. Ils coûtent aussi des
 * tokens à chaque appel.
 */
final class ToolDefinition
{
    /** @param  array<string, mixed>  $parameters  schéma JSON d'un objet (type, properties, required) */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
    ) {}
}
