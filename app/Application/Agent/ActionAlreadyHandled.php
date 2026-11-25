<?php

declare(strict_types=1);

namespace App\Application\Agent;

use RuntimeException;

final class ActionAlreadyHandled extends RuntimeException
{
    public static function make(): self
    {
        return new self('Cette action a déjà été confirmée ou annulée.');
    }
}
