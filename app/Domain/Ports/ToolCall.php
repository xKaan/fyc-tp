<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Une demande d'appel d'outil émise par le modèle, normalisée.
 *
 * Chaque fournisseur a son format : identifiant `id` et arguments en chaîne
 * JSON chez OpenAI et Mistral, pas d'identifiant et arguments en objet chez
 * Ollama. L'adaptateur traduit tout cela en ce type neutre : le cœur métier
 * ne voit jamais le JSON d'un fournisseur.
 *
 * Le modèle ne fait que demander. C'est l'application qui décide d'exécuter,
 * après avoir vérifié les arguments et les droits.
 */
final class ToolCall
{
    /** @param  array<string, mixed>  $arguments  arguments décodés ; tableau vide si le JSON reçu était illisible */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly array $arguments,
    ) {}
}
