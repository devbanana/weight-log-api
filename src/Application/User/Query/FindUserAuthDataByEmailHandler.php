<?php

declare(strict_types=1);

namespace App\Application\User\Query;

use App\Domain\User\ValueObject\Email;

/**
 * Handler for FindUserAuthDataByEmailQuery.
 */
final readonly class FindUserAuthDataByEmailHandler
{
    public function __construct(
        private FindUserAuthData $findUserAuthData,
    ) {
    }

    public function __invoke(FindUserAuthDataByEmailQuery $query): ?UserAuthData
    {
        return $this->findUserAuthData->byEmail(
            Email::fromString($query->email),
        );
    }
}
