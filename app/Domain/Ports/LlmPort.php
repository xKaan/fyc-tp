<?php

declare(strict_types=1);

namespace App\Domain\Ports;

/**
 * Port de sortie : ce dont le cœur métier a besoin pour obtenir une
 * complétion de texte.
 *
 * Le contrat suit la forme commune aux API de modèles (une liste de messages
 * avec un rôle), mais avec les types de l'application : ni URL, ni clé, ni nom
 * de modèle, ni tableau JSON du fournisseur. Cette forme tient jusqu'à la
 * boucle d'agent de la séance 2, où l'on ajoutera l'historique et les appels
 * d'outils sans casser la signature.
 */
interface LlmPort
{
    /**
     * @param  non-empty-list<Message>  $messages  la consigne en rôle system, le contenu utilisateur en rôle user
     *
     * @throws LlmCallFailed quelle que soit la cause de la panne
     */
    public function complete(array $messages): string;
}
