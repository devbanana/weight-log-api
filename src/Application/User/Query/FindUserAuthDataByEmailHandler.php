<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\Email;

/**
 * Handler for FindUserAuthDataByEmailQuery.
 */
final readonly class FindUserAuthDataByEmailHandler
{
    public function __construct(
        private UserReadModelInterface $userReadModel,
    ) {
    }

    public function __invoke(FindUserAuthDataByEmailQuery $query): ?UserAuthData
    {
        $userId = $this->userReadModel->findUserIdByEmail(
            Email::fromString($query->email),
        );

        if ($userId === null) {
            return null;
        }

        return new UserAuthData($userId);
    }
}
