<?php

declare(strict_types=1);

namespace App\Application\Security;

use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;

/**
 * Port for password hashing.
 *
 * Implemented by infrastructure adapters (e.g., Symfony PasswordHasher).
 */
interface PasswordHasherInterface
{
    public function hash(PlainPassword $password): HashedPassword;
}
