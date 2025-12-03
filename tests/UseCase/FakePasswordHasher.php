<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\Security\PasswordHasherInterface;
use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;

/**
 * Fake password hasher for use case tests.
 *
 * Uses real bcrypt but with low cost for fast tests.
 * This allows password verification to work in tests.
 *
 * @internal
 */
final class FakePasswordHasher implements PasswordHasherInterface
{
    #[\Override]
    public function hash(PlainPassword $password): HashedPassword
    {
        // Use real bcrypt with low cost for fast tests
        $hash = password_hash($password->asString(), PASSWORD_BCRYPT, ['cost' => 4]);

        return HashedPassword::fromHash($hash);
    }
}
