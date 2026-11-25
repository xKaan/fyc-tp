<?php

declare(strict_types=1);

namespace App\Application\Agent;

/**
 * Lit les citations d'une réponse. Le prompt demande au modèle de citer
 * chaque note sous la forme [note:12] ; c'est le code qui vérifie ensuite que
 * ces notes existent dans ce qu'il a réellement reçu.
 */
final class Citations
{
    /** @return list<int> les identifiants cités, sans doublon, dans l'ordre d'apparition */
    public static function extract(string $text): array
    {
        preg_match_all('/\[note:(\d+)\]/i', $text, $trouvees);

        return array_values(array_unique(array_map('intval', $trouvees[1])));
    }

    public static function saysDontKnow(string $text): bool
    {
        return str_contains(mb_strtolower($text), 'je ne sais pas');
    }
}
