<?php

declare(strict_types=1);

namespace App\Application;

use App\Casts\AsTagList;
use App\Domain\Ports\LlmPort;
use App\Domain\Ports\Message;
use App\Models\Note;

/**
 * Cas d'usage : suggérer des tags pour une note.
 *
 * Le plafond est une règle de cette fonctionnalité, pas du format de
 * stockage : il vit donc ici, et non dans le cast. Le prompt demande cinq
 * tags au plus, mais c'est le code qui le garantit : on ne délègue pas une
 * règle métier au modèle.
 */
final class SuggestTags
{
    public const MAX_TAGS = 5;

    public function __construct(
        private readonly LlmPort $llm,
        private readonly Prompt $prompt,
    ) {}

    public function __invoke(Note $note): void
    {
        $reponse = $this->llm->complete([
            Message::system($this->prompt->text),
            Message::user($note->title."\n".$note->content),
        ]);

        $note->tags = array_slice(AsTagList::parse($reponse), 0, self::MAX_TAGS);

        $note->save();
    }
}
