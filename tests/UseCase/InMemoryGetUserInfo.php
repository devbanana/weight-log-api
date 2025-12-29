<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\User\Query\GetUserInfo;
use App\Application\User\Query\UserInfo;
use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Exception\CouldNotFindUser;
use App\Domain\User\ValueObject\UserId;

/**
 * In-memory implementation of GetUserInfo for testing.
 *
 * Maintains a projection of user info from domain events.
 *
 * @internal Only to be used in tests
 */
final class InMemoryGetUserInfo implements GetUserInfo
{
    /**
     * @var array<string, UserInfo> userId => UserInfo
     */
    private array $usersById = [];

    public function handleEvent(DomainEventInterface $event): void
    {
        if ($event instanceof UserRegistered) {
            $this->applyUserRegistered($event);
        }
    }

    #[\Override]
    public function byId(UserId $userId): UserInfo
    {
        $userInfo = $this->usersById[$userId->asString()] ?? null;

        if ($userInfo === null) {
            throw CouldNotFindUser::withId($userId);
        }

        return $userInfo;
    }

    private function applyUserRegistered(UserRegistered $event): void
    {
        assert($event->id !== '');
        assert($event->email !== '');
        assert($event->displayName !== '');

        $this->usersById[$event->id] = new UserInfo(
            id: $event->id,
            email: $event->email,
            displayName: $event->displayName,
            registeredAt: $event->occurredAt->format(\DateTimeInterface::ATOM),
        );
    }
}
