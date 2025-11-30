<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserWasRegistered;
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
     * @var array<string, true>
     */
    private array $emailIndex = [];

    /**
     * Handle a domain event to update the projection.
     *
     * This method should be registered as a listener on the event store.
     */
    public function handleEvent(DomainEventInterface $event): void
    {
        if ($event instanceof UserWasRegistered) {
            $this->applyUserWasRegistered($event);
        }
    }

    #[\Override]
    public function existsWithEmail(Email $email): bool
    {
        return isset($this->emailIndex[$email->asString()]);
    }

    private function applyUserWasRegistered(UserWasRegistered $event): void
    {
        // Normalize email to lowercase for case-insensitive matching
        $this->emailIndex[strtolower($event->email)] = true;
    }
}
