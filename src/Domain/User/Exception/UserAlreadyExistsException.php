<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\Email;

final class UserAlreadyExistsException extends \DomainException
{
    public static function withEmail(Email $email): self
    {
        return new self(sprintf('User with email "%s" already exists.', $email->asString()));
    }
}
