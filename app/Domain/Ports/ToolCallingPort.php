<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Port de sortie pour une boucle d'agent : un tour de conversation avec des
 * outils disponibles.
 *
 * Il s'ajoute à LlmPort sans le modifier : les cas d'usage de la séance 1
 * n'ont pas besoin d'outils et gardent leur contrat. Les trois adaptateurs
 * implémentent les deux ports.
 *
 * Ce port ne fait qu'un tour. La boucle (rappeler le modèle tant qu'il
 * demande des outils, s'arrêter au plafond) appartient à l'application :
 * c'est elle que vous écrivez dans le TP S2.4.
 */
interface ToolCallingPort
{
    /**
     * @param  non-empty-list<Message>  $messages  l'historique complet de la conversation
     * @param  list<ToolDefinition>  $tools  les outils que le modèle a le droit de demander
     *
     * @throws LlmCallFailed quelle que soit la cause de la panne
     */
    public function next(array $messages, array $tools): AssistantTurn;
}
