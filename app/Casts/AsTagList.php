<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Centralise le format de stockage des tags.
 *
 * La colonne reste une chaine separee par des virgules : la migration n'est
 * pas touchee par le refactor. Ce qui change, c'est qu'un seul endroit sait
 * comment cette chaine se fabrique, et un seul sait comment elle se relit.
 *
 * @implements CastsAttributes<string|null, string|iterable<string>|null>
 */
final class AsTagList implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : (string) $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $tags = self::parse($value);

        return [$key => $tags === [] ? null : implode(',', $tags)];
    }

    /**
     * Decoupe, nettoie et dedoublonne une liste de tags.
     *
     * Accepte indifferemment la chaine saisie dans un formulaire et le
     * tableau produit par un use-case.
     *
     * @return list<string>
     */
    public static function parse(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $morceaux = is_iterable($value) ? $value : explode(',', (string) $value);

        $tags = [];

        foreach ($morceaux as $morceau) {
            $tag = self::normalize((string) $morceau);

            if ($tag !== '' && ! in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
        }

        return $tags;
    }

    /**
     * Forme canonique d'un tag : minuscules, sans espaces autour, sans diese.
     */
    public static function normalize(string $tag): string
    {
        return trim(str_replace('#', '', mb_strtolower($tag)));
    }
}
