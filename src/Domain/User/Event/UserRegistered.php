<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\Common\Event\DomainEventInterface;

/**
 * Domain event representing a user registration.
 */
final readonly class UserRegistered implements DomainEventInterface
{
    public function __construct(
        public string $id,
        public string $email,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
