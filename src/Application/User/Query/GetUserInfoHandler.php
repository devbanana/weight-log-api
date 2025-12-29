<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\ValueObject\UserId;

/**
 * Handler for GetUserInfoQuery.
 */
final readonly class GetUserInfoHandler
{
    public function __construct(
        private GetUserInfo $getUserInfo,
    ) {
    }

    public function __invoke(GetUserInfoQuery $query): UserInfo
    {
        return $this->getUserInfo->byId(
            UserId::fromString($query->userId),
        );
    }
}
