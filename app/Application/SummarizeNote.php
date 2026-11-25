<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;
use App\Models\Note;

/**
 * Cas d'usage : résumer une note. Ne sait rien du HTTP, ni du fournisseur,
 * ni de l'endroit où le prompt est rangé.
 */
final class SummarizeNote
{
    public function __construct(
        private readonly LlmPort $llm,
        private readonly Prompt $prompt,
    ) {}

    public function __invoke(Note $note): void
    {
        $note->summary = $this->llm->complete([
            Message::system($this->prompt->text),
            Message::user($note->title."\n".$note->content),
        ]);

        $note->save();
    }
}
