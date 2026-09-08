<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Models\Note;
use App\Ports\TextGenerator;

/**
 * Resume une note. Ne sait rien du HTTP, ni du fournisseur d'IA.
 */
final class SummarizeNote
{
    private const INSTRUCTION = 'Resume la note suivante en 3 phrases maximum, en francais. '
        .'Ne reponds que le resume, sans introduction.';

    public function __construct(private readonly TextGenerator $generator) {}

    public function __invoke(Note $note): void
    {
        $note->summary = $this->generator->generate(
            self::INSTRUCTION,
            $note->title."\n".$note->content,
        );

        $note->save();
    }
}
