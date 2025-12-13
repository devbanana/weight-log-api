<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\Email;

final class CouldNotRegister extends \DomainException
{
    public static function becauseEmailIsAlreadyInUse(Email $email): self
    {
        return new self(sprintf('Could not register: email "%s" is already in use.', $email->asString()));
    }
}
