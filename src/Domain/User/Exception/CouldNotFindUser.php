<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\UserId;

/**
 * Exception when a user cannot be found.
 */
final class CouldNotFindUser extends \DomainException
{
    public static function withId(UserId $userId): self
    {
        return new self(sprintf('Could not find user with ID "%s".', $userId->asString()));
    }
}
