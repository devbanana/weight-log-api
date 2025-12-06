<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\Common\Event\DomainEventInterface;

final readonly class UserLoggedIn implements DomainEventInterface
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
