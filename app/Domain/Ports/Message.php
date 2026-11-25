<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Un message envoyé au modèle, dans le vocabulaire de l'application.
 *
 * Les constructeurs nommés empêchent de se tromper de rôle, et chaque
 * adaptateur traduit ce message dans le format JSON de son fournisseur.
 */
final class Message
{
    private function __construct(
        public readonly Role $role,
        public readonly string $content,
    ) {}

    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    public static function user(string $content): self
    {
        return new self(Role::User, $content);
    }

    public static function assistant(string $content): self
    {
        return new self(Role::Assistant, $content);
    }
}
