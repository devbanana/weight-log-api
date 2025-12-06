<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\MessageBus\QueryInterface;

/**
 * Query to find user authentication data by email.
 *
 * Returns UserAuthData (userId + roles) or null if user not found.
 * Used by login flow to look up userId before dispatching LoginCommand.
 *
 * @implements QueryInterface<UserAuthData|null>
 */
final readonly class FindUserAuthDataByEmailQuery implements QueryInterface
{
    public function __construct(
        public string $email,
    ) {
    }
}
