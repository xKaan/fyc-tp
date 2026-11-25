<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Port de vectorisation (embeddings) : un texte devient une liste de nombres,
 * et deux textes proches donnent deux vecteurs proches.
 *
 * Même règle qu'en S2.3 : le même modèle sert à l'indexation et à la
 * recherche. Deux modèles différents produisent des vecteurs qui ne se
 * comparent pas, même s'ils ont la même dimension. modelName() permet de
 * garder, avec chaque vecteur, le modèle qui l'a produit.
 */
interface EmbeddingPort
{
    /**
     * @param  non-empty-list<string>  $texts
     * @return list<list<float>> un vecteur par texte, dans le même ordre
     *
     * @throws LlmCallFailed
     */
    public function embed(array $texts): array;

    public function modelName(): string;
}
