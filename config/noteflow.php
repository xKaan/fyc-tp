<?php

/*
 * Réglages de l'agent NoteFlow (TP S2.4).
 */

return [

    'agent' => [
        // Plafond d'étapes : au plus N appels au modèle pour une question.
        // C'est une condition d'arrêt et un garde-fou de coût (S1.2) : une
        // boucle sans plafond peut tourner, et facturer, sans fin.
        'max_steps' => (int) env('NOTEFLOW_AGENT_MAX_STEPS', 5),
    ],

];
