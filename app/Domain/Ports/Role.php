<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Qui parle dans un message. La séparation entre la consigne (system) et le
 * contenu fourni par l'utilisateur (user) est la première ligne de défense
 * contre l'injection de prompt : elle ne suffit pas seule (séance 4), mais
 * sans elle aucune autre défense n'est possible.
 */
enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
}
