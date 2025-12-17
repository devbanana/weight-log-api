<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Domain\User\Exception\CouldNotRegister;
use App\Domain\User\User;
use App\Domain\User\ValueObject\DateOfBirth;
use App\Domain\User\ValueObject\DisplayName;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(User::class)]
#[UsesClass(UserId::class)]
#[UsesClass(Email::class)]
#[UsesClass(DateOfBirth::class)]
#[UsesClass(DisplayName::class)]
#[UsesClass(HashedPassword::class)]
#[UsesClass(PlainPassword::class)]
final class UserTest extends TestCase
{
    private const string DEFAULT_USER_ID = '550e8400-e29b-41d4-a716-446655440000';
    private const string DEFAULT_EMAIL = 'test@example.com';
    private const string DEFAULT_DATE_OF_BIRTH = '1990-05-15';
    private const string DEFAULT_DISPLAY_NAME = 'Test User';
    private const string DEFAULT_PASSWORD_HASH = '$2y$10$hashedpassword';
    private const string VERIFIABLE_PLAIN_PASSWORD = 'SecurePass123!';
    private const int TEST_BCRYPT_COST = 4;

    public function testRegisterRecordsUserRegisteredEvent(): void
    {
        $registeredAt = self::createRegisteredAt();
        $user = self::registerUser(registeredAt: $registeredAt);

        $events = $user->releaseEvents();

        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(UserRegistered::class, $event);
        self::assertSame(self::DEFAULT_USER_ID, $event->id);
        self::assertSame(self::DEFAULT_EMAIL, $event->email);
        self::assertSame(self::DEFAULT_DATE_OF_BIRTH, $event->dateOfBirth);
        self::assertSame(self::DEFAULT_DISPLAY_NAME, $event->displayName);
        self::assertSame(self::DEFAULT_PASSWORD_HASH, $event->hashedPassword);
        self::assertSame($registeredAt, $event->occurredAt);
    }

    public function testRegisterThrowsExceptionWhenUserIsUnder18(): void
    {
        $this->expectException(CouldNotRegister::class);
        $this->expectExceptionMessage('user must be at least 18 years old (age: 15)');

        self::registerUser(dateOfBirth: '2010-06-15'); // 15 years old on 2025-12-12
    }

    public function testRegisterSucceedsWhenUserIsExactly18(): void
    {
        $user = self::registerUser(dateOfBirth: '2007-12-12'); // Exactly 18 on 2025-12-12

        $events = $user->releaseEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(UserRegistered::class, $events[0]);
    }

    public function testRegisterThrowsExceptionWhenDateOfBirthIsInTheFuture(): void
    {
        $this->expectException(CouldNotRegister::class);
        $this->expectExceptionMessage('date of birth cannot be in the future');

        self::registerUser(dateOfBirth: '2030-01-01'); // Future date
    }

    public function testItReconstitutesUserFromEventsWithoutRecordingNewEvents(): void
    {
        $user = User::reconstitute([self::createUserRegisteredEvent()]);

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
        $user = User::reconstitute([
            self::createUserRegisteredEvent(passwordHash: self::createVerifiablePasswordHash()),
        ]);

        $loginTimestamp = self::createLoginTimestamp();
        $user->login(PlainPassword::fromString(self::VERIFIABLE_PLAIN_PASSWORD), $loginTimestamp);

        $events = $user->releaseEvents();
        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(UserLoggedIn::class, $event);
        self::assertSame(self::DEFAULT_USER_ID, $event->id);
        self::assertSame($loginTimestamp, $event->occurredAt);
    }

    public function testLoginThrowsExceptionForInvalidPassword(): void
    {
        $user = User::reconstitute([
            self::createUserRegisteredEvent(passwordHash: self::createVerifiablePasswordHash()),
        ]);

        $this->expectException(CouldNotAuthenticate::class);

        $user->login(PlainPassword::fromString('WrongPassword123!'), self::createLoginTimestamp());
    }

    public function testItCanReconstituteUserWithLoginEvent(): void
    {
        $user = User::reconstitute([
            self::createUserRegisteredEvent(),
            new UserLoggedIn(self::DEFAULT_USER_ID, self::createLoginTimestamp()),
        ]);

        // No errors and no new events recorded
        self::assertEmpty($user->releaseEvents());
    }

    private static function createRegisteredAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-12-12 12:00:00', new \DateTimeZone('UTC'));
    }

    private static function createLoginTimestamp(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2025-12-13 10:00:00', new \DateTimeZone('UTC'));
    }

    private static function createVerifiablePasswordHash(): string
    {
        return password_hash(self::VERIFIABLE_PLAIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => self::TEST_BCRYPT_COST]);
    }

    private static function registerUser(
        ?string $userId = null,
        ?string $email = null,
        ?string $dateOfBirth = null,
        ?string $displayName = null,
        ?string $passwordHash = null,
        ?\DateTimeImmutable $registeredAt = null,
    ): User {
        return User::register(
            UserId::fromString($userId ?? self::DEFAULT_USER_ID),
            Email::fromString($email ?? self::DEFAULT_EMAIL),
            DateOfBirth::fromString($dateOfBirth ?? self::DEFAULT_DATE_OF_BIRTH),
            DisplayName::fromString($displayName ?? self::DEFAULT_DISPLAY_NAME),
            HashedPassword::fromHash($passwordHash ?? self::DEFAULT_PASSWORD_HASH),
            $registeredAt ?? self::createRegisteredAt(),
        );
    }

    private static function createUserRegisteredEvent(
        ?string $userId = null,
        ?string $email = null,
        ?string $dateOfBirth = null,
        ?string $displayName = null,
        ?string $passwordHash = null,
        ?\DateTimeImmutable $occurredAt = null,
    ): UserRegistered {
        return new UserRegistered(
            $userId ?? self::DEFAULT_USER_ID,
            $email ?? self::DEFAULT_EMAIL,
            $dateOfBirth ?? self::DEFAULT_DATE_OF_BIRTH,
            $displayName ?? self::DEFAULT_DISPLAY_NAME,
            $passwordHash ?? self::DEFAULT_PASSWORD_HASH,
            $occurredAt ?? self::createRegisteredAt(),
        );
    }
}
