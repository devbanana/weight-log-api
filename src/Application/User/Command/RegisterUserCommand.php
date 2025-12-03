<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\MessageBus\CommandInterface;

/**
 * Command to register a new user.
 *
 * This is a simple DTO (Data Transfer Object) that carries data from the presentation layer
 * to the application layer. It uses primitive types (not domain objects) following CQRS principles.
 */
final readonly class RegisterUserCommand implements CommandInterface
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $password,
    ) {
    }
}
