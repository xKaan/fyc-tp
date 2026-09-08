<?php

declare(strict_types=1);

namespace App\UseCases;

use App\Casts\AsTagList;
use App\Models\Note;
use App\Ports\TextGenerator;

/**
 * Suggere des tags pour une note.
 *
 * Le plafond est une regle de cette fonctionnalite, pas du format de
 * stockage : il vit donc ici, et non dans le cast.
 */
final class SuggestTags
{
    public const MAX_TAGS = 5;

    private const INSTRUCTION = 'Donne au maximum 5 tags pour la note suivante. '
        .'Reponds uniquement avec les tags separes par des virgules.';

    public function __construct(private readonly TextGenerator $generator) {}

    public function __invoke(Note $note): void
    {
        $reponse = $this->generator->generate(
            self::INSTRUCTION,
            $note->title."\n".$note->content,
        );

        $note->tags = array_slice(AsTagList::parse($reponse), 0, self::MAX_TAGS);

        $note->save();
    }
}
