<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

use App\Domain\User\ValueObject\Email;

final class CouldNotRegister extends \DomainException
{
    public readonly RegistrationFailureReason $reason;

    private function __construct(string $message, RegistrationFailureReason $reason)
    {
        parent::__construct($message);
        $this->reason = $reason;
    }

    public static function becauseEmailIsAlreadyInUse(Email $email): self
    {
        return new self(
            sprintf('Could not register: email "%s" is already in use.', $email->asString()),
            RegistrationFailureReason::EmailAlreadyInUse,
        );
    }

    public static function becauseUserIsTooYoung(int $age, int $minimumAge): self
    {
        return new self(
            sprintf('Could not register: user must be at least %d years old (age: %d).', $minimumAge, $age),
            RegistrationFailureReason::UserTooYoung,
        );
    }

    public static function becauseDateOfBirthIsInTheFuture(): self
    {
        return new self(
            'Could not register: date of birth cannot be in the future.',
            RegistrationFailureReason::DateOfBirthInTheFuture,
        );
    }
}
