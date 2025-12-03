<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\User\User
 *
 * @internal
 */
final class UserTest extends TestCase
{
    public function testItRecordsUserRegisteredEvent(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = Email::fromString('test@example.com');
        $password = HashedPassword::fromHash('$2y$10$hashedpassword');
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));

        $user = User::register($userId, $email, $password, $registeredAt);

        $events = $user->releaseEvents();

        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $event->id);
        self::assertSame('test@example.com', $event->email);
        self::assertSame('$2y$10$hashedpassword', $event->hashedPassword);
        self::assertSame($registeredAt, $event->occurredAt);
    }

    public function testItReconstitutesUserFromEvents(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $email = 'test@example.com';
        $passwordHash = '$2y$10$hashedpassword';
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));

        $events = [
            new UserRegistered($userId, $email, $passwordHash, $registeredAt),
        ];

        $user = User::reconstitute($events);

        // Verify aggregate was reconstituted by checking it doesn't record new events
        self::assertEmpty($user->releaseEvents());
    }

    public function testItDoesNotRecordEventsWhenReconstituting(): void
    {
        $events = [
            new UserRegistered(
                '550e8400-e29b-41d4-a716-446655440000',
                'test@example.com',
                '$2y$10$hashedpassword',
                new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC')),
            ),
        ];

        $user = User::reconstitute($events);

        // reconstitute() should NOT record events, only apply them
        self::assertEmpty($user->releaseEvents());
    }

    public function testItThrowsExceptionForUnknownEvent(): void
    {
        $unknownEvent = new class('user-123', new \DateTimeImmutable()) implements DomainEventInterface {
            public function __construct(
                public string $id,
                public \DateTimeImmutable $occurredAt,
            ) {
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown event type');

        User::reconstitute([$unknownEvent]);
    }
}
