<?php

declare(strict_types=1);

namespace App\Domain\Ports;

use App\Models\User;

/**
 * Port de recherche dans les notes.
 *
 * Le dépôt fournit une recherche par mots-clés (KeywordNoteSearch). La
 * recherche plein texte et vectorielle de S2.3 se branche sur ce même port :
 * l'outil chercher_notes et la boucle d'agent ne changent pas.
 *
 * Contrat : seules les notes que l'utilisateur a le droit de lire sortent, et
 * ce filtre est fait dans la requête, jamais après coup ni par le prompt.
 */
interface NoteSearch
{
    /** @return list<NoteHit> les notes trouvées, la plus pertinente d'abord */
    public function search(string $question, User $user, int $limit = 5): array;
}
