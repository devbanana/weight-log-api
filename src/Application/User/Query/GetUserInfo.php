<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\Exception\CouldNotFindUser;
use App\Domain\User\ValueObject\UserId;

/**
 * Retrieves user information for display.
 *
 * Returns a UserInfo view model containing the user's
 * public profile information.
 */
interface GetUserInfo
{
    /**
     * @throws CouldNotFindUser When the user does not exist
     */
    public function byId(UserId $userId): UserInfo;
}
