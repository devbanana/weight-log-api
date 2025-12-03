<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Security\PasswordHasherInterface;
use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;

/**
 * Production password hasher using PHP's native password hashing.
 *
 * Uses bcrypt with default cost (currently 10) for secure password hashing.
 */
final readonly class NativePasswordHasher implements PasswordHasherInterface
{
    #[\Override]
    public function hash(PlainPassword $password): HashedPassword
    {
        $hash = password_hash($password->asString(), PASSWORD_BCRYPT);

        return HashedPassword::fromHash($hash);
    }
}
