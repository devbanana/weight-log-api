<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\ValueObject\Email;

/**
 * Finder for retrieving user authentication data.
 *
 * Returns a UserAuthData read model containing the information
 * needed for authentication (userId, roles).
 */
interface FindUserAuthData
{
    public function byEmail(Email $email): ?UserAuthData;
}
