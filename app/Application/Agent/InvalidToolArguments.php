<?php

declare(strict_types=1);

namespace App\Application\Agent;

use Illuminate\Contracts\Validation\Validator;
use RuntimeException;

/**
 * Les arguments proposés par le modèle sont refusés par le code.
 *
 * Le message est renvoyé au modèle comme résultat d'outil : il doit être
 * assez précis pour qu'il corrige son appel au tour suivant. Il ne doit rien
 * révéler que l'utilisateur n'a pas le droit de savoir.
 */
final class InvalidToolArguments extends RuntimeException
{
    public static function fromValidator(Validator $validator): self
    {
        return new self('Arguments refusés : '.implode(' ', $validator->errors()->all()));
    }
}
