<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Ports\TextGenerator;

/**
 * Implementation en memoire du port, utilisee par les tests.
 *
 * Elle tient dans trente lignes parce que le contrat est etroit. C'est le
 * meilleur indicateur qu'une frontiere est bien posee.
 */
final class FakeTextGenerator implements TextGenerator
{
    /** @var list<array{instruction: string, input: string}> */
    public array $calls = [];

    public function __construct(
        private readonly string $answer = 'texte genere',
        private readonly bool $shouldFail = false,
    ) {}

    public static function failing(): self
    {
        return new self(shouldFail: true);
    }

    public function generate(string $instruction, string $input): string
    {
        $this->calls[] = ['instruction' => $instruction, 'input' => $input];

        if ($this->shouldFail) {
            throw TextGenerationFailed::unreachable('fake');
        }

        return $this->answer;
    }
}
