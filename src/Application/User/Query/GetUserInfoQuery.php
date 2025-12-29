<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Application\MessageBus\QueryInterface;

/**
 * Query to get user information by ID.
 *
 * @implements QueryInterface<UserInfo>
 */
final readonly class GetUserInfoQuery implements QueryInterface
{
    /**
     * @param non-empty-string $userId
     */
    public function __construct(
        public string $userId,
    ) {
    }
}
