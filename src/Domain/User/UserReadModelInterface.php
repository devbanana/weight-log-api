<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\User\ValueObject\Email;

/**
 * Port for querying user read models (projections).
 *
 * This is the read side of CQRS - queries go directly to projections,
 * not through the event-sourced aggregate.
 */
interface UserReadModelInterface
{
    /**
     * Check if a user exists with the given email.
     *
     * Used for uniqueness validation during registration.
     */
    public function existsWithEmail(Email $email): bool;
}
