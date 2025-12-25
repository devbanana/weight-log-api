<?php

declare(strict_types=1);

namespace App\Domain\User\Service;

use App\Domain\User\ValueObject\Email;

interface CheckEmail
{
    /**
     * Check if an email address is unique (not already in use by another user).
     */
    public function isUnique(Email $email): bool;
}
