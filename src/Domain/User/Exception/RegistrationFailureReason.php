<?php

declare(strict_types=1);

namespace App\Domain\User\Exception;

enum RegistrationFailureReason
{
    case EmailAlreadyInUse;
    case UserTooYoung;
    case DateOfBirthInTheFuture;
}
