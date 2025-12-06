<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Domain\User\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;
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

    public function testItReconstitutesUserFromEventsWithoutRecordingNewEvents(): void
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

        // reconstitute() should apply events but NOT record new ones
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

    public function testLoginRecordsUserLoggedInEvent(): void
    {
        // Arrange: Create a user with known password
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = Email::fromString('test@example.com');
        $plainPassword = PlainPassword::fromString('SecurePass123!');
        $hashedPassword = HashedPassword::fromHash(password_hash('SecurePass123!', PASSWORD_BCRYPT, ['cost' => 4]));
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));

        $user = User::register($userId, $email, $hashedPassword, $registeredAt);
        $user->releaseEvents(); // Clear registration event

        // Act: Login with correct password
        $loginTimestamp = new \DateTimeImmutable('2025-11-28 10:00:00', new \DateTimeZone('UTC'));
        $user->login($plainPassword, $loginTimestamp);

        // Assert: UserLoggedIn event was recorded
        $events = $user->releaseEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(UserLoggedIn::class, $event);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $event->id);
        self::assertSame($loginTimestamp, $event->occurredAt);
    }

    public function testLoginThrowsExceptionForInvalidPassword(): void
    {
        // Arrange: Create a user with known password
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = Email::fromString('test@example.com');
        $hashedPassword = HashedPassword::fromHash(password_hash('SecurePass123!', PASSWORD_BCRYPT, ['cost' => 4]));
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));

        $user = User::register($userId, $email, $hashedPassword, $registeredAt);
        $user->releaseEvents(); // Clear registration event

        // Act & Assert: Login with wrong password throws exception
        $wrongPassword = PlainPassword::fromString('WrongPassword123!');
        $loginTimestamp = new \DateTimeImmutable('2025-11-28 10:00:00', new \DateTimeZone('UTC'));

        $this->expectException(CouldNotAuthenticate::class);

        $user->login($wrongPassword, $loginTimestamp);
    }

    public function testReconstitutesUserWithLoginEvent(): void
    {
        // Arrange: Create event stream with registration + login
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $email = 'test@example.com';
        $passwordHash = '$2y$10$hashedpassword';
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));
        $loginTimestamp = new \DateTimeImmutable('2025-11-28 10:00:00', new \DateTimeZone('UTC'));

        $events = [
            new UserRegistered($userId, $email, $passwordHash, $registeredAt),
            new UserLoggedIn($userId, $loginTimestamp),
        ];

        // Act: Reconstitute user
        $user = User::reconstitute($events);

        // Assert: No errors and no new events recorded
        self::assertEmpty($user->releaseEvents());
    }

    public function testReconstitutedUserCanLogin(): void
    {
        // Arrange: Create event with real bcrypt hash and reconstitute
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $passwordHash = password_hash('SecurePass123!', PASSWORD_BCRYPT, ['cost' => 4]);
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));

        $events = [
            new UserRegistered($userId, 'test@example.com', $passwordHash, $registeredAt),
        ];

        $user = User::reconstitute($events);

        // Act: Login with correct password
        $loginTimestamp = new \DateTimeImmutable('2025-11-28 10:00:00', new \DateTimeZone('UTC'));
        $user->login(PlainPassword::fromString('SecurePass123!'), $loginTimestamp);

        // Assert: UserLoggedIn event was recorded
        $events = $user->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(UserLoggedIn::class, $events[0]);
    }
}
