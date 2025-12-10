<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Domain\User\User;
use App\Domain\User\ValueObject\PlainPassword;
use Psr\Clock\ClockInterface;

/**
 * Handler for LoginCommand.
 *
 * Loads the user aggregate, verifies credentials, and records login event.
 */
final readonly class LoginHandler
{
    public function __construct(
        private EventStoreInterface $eventStore,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LoginCommand $command): void
    {
        $userId = $command->userId;

        // Load aggregate from events
        $events = $this->eventStore->getEvents($userId, User::class);
        $version = $this->eventStore->getVersion($userId, User::class);

        if ($version === 0) {
            throw CouldNotAuthenticate::becauseInvalidCredentials();
        }

        $user = User::reconstitute($events);

        // Execute login behavior (throws if invalid password)
        $user->login(
            PlainPassword::fromString($command->password),
            $this->clock->now(),
        );

        // Persist UserLoggedIn event
        $events = $user->releaseEvents();
        assert(count($events) > 0);

        $this->eventStore->append(
            $userId,
            User::class,
            $events,
            expectedVersion: $version,
        );
    }
}
