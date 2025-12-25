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
     * Find user ID by email.
     *
     * Used for looking up user during login.
     *
     * @return non-empty-string|null User ID or null if not found
     */
    public function findUserIdByEmail(Email $email): ?string;
}
