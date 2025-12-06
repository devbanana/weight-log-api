<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Projection;

use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Event\UserRegistered;
use App\Infrastructure\Projection\UserProjection;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for UserProjection.
 *
 * Tests that the projection correctly updates the MongoDB users collection
 * when domain events are received.
 *
 * @internal
 */
#[CoversClass(UserProjection::class)]
final class UserProjectionTest extends TestCase
{
    use MongoHelper;

    private Collection $collection;
    private UserProjection $projection;

    #[\Override]
    protected function setUp(): void
    {
        $mongoUrl = $_ENV['MONGODB_URL'];
        self::assertIsString($mongoUrl, 'MONGODB_URL must be set in environment for tests');
        $database = $_ENV['MONGODB_DATABASE'];
        self::assertIsString($database, 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);
        $this->collection = $client->selectCollection($database, 'users');
        $this->collection->drop();

        $this->projection = new UserProjection($this->collection);
    }

    public function testItProjectsUserRegisteredEventToReadModel(): void
    {
        $event = new UserRegistered(
            id: 'user-123',
            email: 'john@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable('2025-01-15T10:30:00+00:00'),
        );

        self::assertNull($this->collection->findOne(['_id' => 'user-123']));

        $this->projection->onUserRegistered($event);

        $document = $this->findDocument('user-123');
        self::assertSame('john@example.com', $document['email']);
    }

    public function testItStoresEmailAsProvidedByDomain(): void
    {
        // Email normalization is a domain concern - the projection stores exactly what it receives
        $event = new UserRegistered(
            id: 'user-456',
            email: 'John.Doe@Example.COM', // Mixed case to verify no normalization
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->projection->onUserRegistered($event);

        // Verify the exact value stored in MongoDB (not via read model which might normalize)
        $document = $this->findDocument('user-456');
        self::assertSame('John.Doe@Example.COM', $document['email']);
    }

    public function testUserRegisteredIsIdempotent(): void
    {
        $event = new UserRegistered(
            id: 'user-idempotent',
            email: 'idempotent@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );

        // Apply the same event twice
        $this->projection->onUserRegistered($event);
        $this->projection->onUserRegistered($event);

        // Should still have only one document
        $count = $this->collection->countDocuments(['_id' => 'user-idempotent']);
        self::assertSame(1, $count);
    }

    public function testUserRegisteredHandlesMultipleUsers(): void
    {
        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-1',
            email: 'first@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        ));

        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-2',
            email: 'second@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        ));

        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-3',
            email: 'third@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        ));

        // Verify all three documents exist
        $this->findDocument('user-1');
        $this->findDocument('user-2');
        $this->findDocument('user-3');

        self::assertSame(3, $this->collection->countDocuments([]));
    }

    public function testItStoresRegistrationTimestamp(): void
    {
        $registeredAt = new \DateTimeImmutable('2025-06-15T14:30:00+00:00');
        $event = new UserRegistered(
            id: 'user-timestamp',
            email: 'timestamp@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: $registeredAt,
        );

        $this->projection->onUserRegistered($event);

        $document = $this->findDocument('user-timestamp');
        self::assertArrayHasKey('registered_at', (array) $document);

        $storedDate = $document['registered_at'];
        self::assertInstanceOf(UTCDateTime::class, $storedDate);
        self::assertDateTimeEquals($registeredAt, $storedDate);
    }

    public function testItStoresHashedPassword(): void
    {
        $event = new UserRegistered(
            id: 'user-password-test',
            email: 'password-test@example.com',
            hashedPassword: '$2y$10$abcdefghijklmnopqrstuv',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->projection->onUserRegistered($event);

        $document = $this->findDocument('user-password-test');
        self::assertArrayHasKey('hashed_password', (array) $document);
        self::assertSame('$2y$10$abcdefghijklmnopqrstuv', $document['hashed_password']);
    }

    public function testItUpdatesLastLoginAtForExistingUser(): void
    {
        // First, register the user
        $registeredEvent = new UserRegistered(
            id: 'user-login-test',
            email: 'login@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable('2025-01-15T10:00:00+00:00'),
        );
        $this->projection->onUserRegistered($registeredEvent);

        // Then, log in the user
        $loginAt = new \DateTimeImmutable('2025-01-15T14:30:00+00:00');
        $loginEvent = new UserLoggedIn(
            id: 'user-login-test',
            occurredAt: $loginAt,
        );
        $this->projection->onUserLoggedIn($loginEvent);

        // Verify last_login_at was updated
        $document = $this->findDocument('user-login-test');
        self::assertArrayHasKey('last_login_at', (array) $document);

        $lastLoginAt = $document['last_login_at'];
        self::assertInstanceOf(UTCDateTime::class, $lastLoginAt);
        self::assertDateTimeEquals($loginAt, $lastLoginAt);
    }

    public function testItUpdatesLastLoginAtOnSubsequentLogins(): void
    {
        // Register user first
        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-multi-login',
            email: 'multi-login@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable('2025-01-01T00:00:00+00:00'),
        ));

        // First login
        $firstLoginAt = new \DateTimeImmutable('2025-01-15T10:00:00+00:00');
        $this->projection->onUserLoggedIn(new UserLoggedIn(
            id: 'user-multi-login',
            occurredAt: $firstLoginAt,
        ));

        $document = $this->findDocument('user-multi-login');
        $firstLastLoginAt = $document['last_login_at'];
        self::assertInstanceOf(UTCDateTime::class, $firstLastLoginAt);
        self::assertDateTimeEquals($firstLoginAt, $firstLastLoginAt);

        // Second login with later timestamp
        $secondLoginAt = new \DateTimeImmutable('2025-01-20T14:30:00+00:00');
        $this->projection->onUserLoggedIn(new UserLoggedIn(
            id: 'user-multi-login',
            occurredAt: $secondLoginAt,
        ));

        $document = $this->findDocument('user-multi-login');
        $secondLastLoginAt = $document['last_login_at'];
        self::assertInstanceOf(UTCDateTime::class, $secondLastLoginAt);
        self::assertDateTimeEquals($secondLoginAt, $secondLastLoginAt);
    }

    public function testUserLoggedInIsIdempotent(): void
    {
        // Register user first
        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-idempotent-login',
            email: 'idempotent-login@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        ));

        $loginEvent = new UserLoggedIn(
            id: 'user-idempotent-login',
            occurredAt: new \DateTimeImmutable('2025-01-15T12:00:00+00:00'),
        );

        // Apply the same login event twice
        $this->projection->onUserLoggedIn($loginEvent);
        $this->projection->onUserLoggedIn($loginEvent);

        // Should still have only one document with the same last_login_at
        $count = $this->collection->countDocuments(['_id' => 'user-idempotent-login']);
        self::assertSame(1, $count);

        $document = $this->findDocument('user-idempotent-login');
        $lastLoginAt = $document['last_login_at'];
        self::assertInstanceOf(UTCDateTime::class, $lastLoginAt);
        self::assertDateTimeEquals(
            new \DateTimeImmutable('2025-01-15T12:00:00+00:00'),
            $lastLoginAt,
        );
    }
}
