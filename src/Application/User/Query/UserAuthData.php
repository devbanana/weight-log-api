<?php

declare(strict_types=1);

namespace App\Application\User\Query;

/**
 * Data returned by FindUserAuthDataByEmailQuery.
 *
 * Contains the information needed for authentication:
 * - userId: to dispatch LoginCommand
 * - roles: to generate JWT token with correct claims
 */
final readonly class UserAuthData
{
    /**
     * @param non-empty-string $userId
     * @param list<string>     $roles
     */
    public function __construct(
        public string $userId,
        public array $roles = ['ROLE_USER'],
    ) {
    }
}
