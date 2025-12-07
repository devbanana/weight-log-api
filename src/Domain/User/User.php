<?php

declare(strict_types=1);

namespace App\Domain\User;

use App\Domain\Common\Aggregate\EventSourcedAggregateInterface;
use App\Domain\Common\Aggregate\RecordsEvents;
use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;
use App\Domain\User\ValueObject\UserId;

/**
 * User aggregate root.
 *
 * State is derived from domain events. Use register() to create a new user,
 * or reconstitute() to rebuild from an event stream.
 */
final class User implements EventSourcedAggregateInterface
{
    use RecordsEvents;

    private UserId $id;
    private Email $email;
    private HashedPassword $password;

    private function __construct()
    {
        // State is set via apply methods when events are recorded/replayed
    }

    /**
     * Register a new user.
     */
    public static function register(
        UserId $id,
        Email $email,
        HashedPassword $password,
        \DateTimeImmutable $registeredAt,
    ): self {
        $user = new self();
        $user->recordThat(new UserRegistered(
            $id->asString(),
            $email->asString(),
            $password->asString(),
            $registeredAt,
        ));

        return $user;
    }

    public function login(PlainPassword $password, \DateTimeImmutable $now): void
    {
        if (!$this->password->verify($password)) {
            throw CouldNotAuthenticate::becauseInvalidCredentials();
        }

        $this->recordThat(new UserLoggedIn(
            $this->id->asString(),
            $now,
        ));
    }

    /**
     * Reconstitute a user from an event stream.
     *
     * @param list<DomainEventInterface> $events
     */
    #[\Override]
    public static function reconstitute(array $events): static
    {
        $user = new self();
        foreach ($events as $event) {
            $user->apply($event);
        }

        return $user;
    }

    #[\Override]
    private function apply(DomainEventInterface $event): void
    {
        match ($event::class) {
            UserRegistered::class => $this->applyUserRegistered($event),
            UserLoggedIn::class => self::applyUserLoggedIn($event),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown event type "%s" for User aggregate',
                $event::class,
            )),
        };
    }

    private function applyUserRegistered(UserRegistered $event): void
    {
        $this->id = UserId::fromString($event->id);
        $this->email = Email::fromString($event->email);
        $this->password = HashedPassword::fromHash($event->hashedPassword);
    }

    private static function applyUserLoggedIn(UserLoggedIn $event): void
    {
        // Login events don't modify aggregate state beyond what's tracked in the event store
    }
}
