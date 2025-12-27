<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Service\CheckEmail;
use App\Domain\User\ValueObject\Email;

/**
 * In-memory implementation of CheckEmail for testing.
 *
 * Maintains a set of taken email addresses, updated via domain events.
 *
 * @internal Only to be used in tests
 */
final class InMemoryCheckEmail implements CheckEmail
{
    /**
     * @var array<string, true> Set of taken email addresses
     */
    private array $takenEmails = [];

    /**
     * Handle a domain event to update the projection.
     *
     * This method should be registered as a listener on the event store.
     */
    public function handleEvent(DomainEventInterface $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->takenEmails[$event->email] = true;
        }
    }

    #[\Override]
    public function isUnique(Email $email): bool
    {
        return !isset($this->takenEmails[$email->asString()]);
    }
}
