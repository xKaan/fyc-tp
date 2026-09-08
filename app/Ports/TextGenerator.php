<?php

declare(strict_types=1);

namespace App\Ports;

use App\Adapters\TextGenerationFailed;

/**
 * Ce dont l'application a besoin pour generer du texte, dit dans son propre
 * vocabulaire.
 *
 * Volontairement etroit : ni modele, ni temperature, ni cle d'API dans la
 * signature. Ce sont des details d'implementation, pas du vocabulaire metier.
 * Cette etroitesse est ce qui rend le contrat stable quand le fournisseur
 * change.
 */
interface TextGenerator
{
    /**
     * @param  string  $instruction  ce que le modele doit faire
     * @param  string  $input  le contenu utilisateur, jamais concatene a l'instruction
     *
     * @throws TextGenerationFailed
     */
    public function generate(string $instruction, string $input): string;
}
