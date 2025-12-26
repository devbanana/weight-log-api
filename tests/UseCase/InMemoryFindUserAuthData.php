<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\User\Query\FindUserAuthData;
use App\Application\User\Query\UserAuthData;
use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\ValueObject\Email;

/**
 * In-memory implementation of FindUserAuthData for testing.
 *
 * Maintains a projection of user auth data from domain events.
 *
 * @internal Only to be used in tests
 */
final class InMemoryFindUserAuthData implements FindUserAuthData
{
    /**
     * @var array<string, UserAuthData> email => UserAuthData
     */
    private array $usersByEmail = [];

    public function handleEvent(DomainEventInterface $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->applyUserRegistered($event);
        }
    }

    #[\Override]
    public function byEmail(Email $email): ?UserAuthData
    {
        return $this->usersByEmail[$email->asString()] ?? null;
    }

    private function applyUserRegistered(UserRegistered $event): void
    {
        assert($event->id !== '');
        $this->usersByEmail[$event->email] = new UserAuthData($event->id);
    }
}
