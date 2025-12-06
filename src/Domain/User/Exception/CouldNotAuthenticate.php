<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

final class CouldNotAuthenticate extends \DomainException
{
    public static function becauseInvalidCredentials(): self
    {
        return new self('Could not authenticate: invalid credentials.');
    }
}
