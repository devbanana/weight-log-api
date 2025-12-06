<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\MessageBus\CommandInterface;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;

/**
 * Command to log in a user.
 *
 * The userId is looked up by email before dispatching this command.
 * The handler verifies the password and records a UserLoggedIn event.
 *
 * @see FindUserAuthDataByEmailQuery
 */
final readonly class LoginCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $password,
    ) {
    }
}
