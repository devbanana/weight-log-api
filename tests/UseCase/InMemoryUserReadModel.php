<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\Email;

/**
 * In-memory implementation of UserReadModel for testing.
 *
 * This is a projection that maintains a queryable view of users.
 * It gets updated when domain events are dispatched.
 *
 * @internal Only to be used in tests
 */
final class InMemoryUserReadModel implements UserReadModelInterface
{
    /**
     * @var array<string, non-empty-string> email => userId
     */
    private array $emailToUserId = [];

    /**
     * Handle a domain event to update the projection.
     *
     * This method should be registered as a listener on the event store.
     */
    public function handleEvent(DomainEventInterface $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->applyUserRegistered($event);
        }
    }

    #[\Override]
    public function findUserIdByEmail(Email $email): ?string
    {
        return $this->emailToUserId[$email->asString()] ?? null;
    }

    private function applyUserRegistered(UserRegistered $event): void
    {
        assert($event->id !== '');
        $this->emailToUserId[$event->email] = $event->id;
    }
}
