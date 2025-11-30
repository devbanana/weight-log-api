<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Projection;

use App\Domain\User\Event\UserRegistered;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Persistence\MongoDB\MongoUserReadModel;
use App\Infrastructure\Projection\UserProjection;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for UserProjection.
 *
 * Tests that the projection correctly updates the MongoDB users collection
 * when domain events are received.
 *
 * @covers \App\Infrastructure\Projection\UserProjection
 *
 * @internal
 */
final class UserProjectionTest extends TestCase
{
    private Collection $collection;
    private UserProjection $projection;
    private MongoUserReadModel $readModel;

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
        $this->readModel = new MongoUserReadModel($this->collection);
    }

    public function testItProjectsUserRegisteredEventToReadModel(): void
    {
        $event = new UserRegistered(
            id: 'user-123',
            email: 'john@example.com',
            occurredAt: new \DateTimeImmutable('2025-01-15T10:30:00+00:00'),
        );

        $this->projection->onUserRegistered($event);

        self::assertTrue($this->readModel->existsWithEmail(Email::fromString('john@example.com')));
    }

    public function testItStoresEmailAsProvidedByDomain(): void
    {
        // Email normalization is a domain concern - the projection trusts the event data
        $event = new UserRegistered(
            id: 'user-456',
            email: 'john.doe@example.com', // Already normalized by domain
            occurredAt: new \DateTimeImmutable(),
        );

        $this->projection->onUserRegistered($event);

        self::assertTrue($this->readModel->existsWithEmail(Email::fromString('john.doe@example.com')));
    }

    public function testItStoresUserIdAsDocumentId(): void
    {
        $event = new UserRegistered(
            id: 'user-789',
            email: 'jane@example.com',
            occurredAt: new \DateTimeImmutable(),
        );

        $this->projection->onUserRegistered($event);

        $document = $this->collection->findOne(['_id' => 'user-789']);
        self::assertNotNull($document);
        self::assertInstanceOf(BSONDocument::class, $document);
        self::assertSame('jane@example.com', $document['email']);
    }

    public function testItIsIdempotent(): void
    {
        $event = new UserRegistered(
            id: 'user-idempotent',
            email: 'idempotent@example.com',
            occurredAt: new \DateTimeImmutable(),
        );

        // Apply the same event twice
        $this->projection->onUserRegistered($event);
        $this->projection->onUserRegistered($event);

        // Should still have only one document
        $count = $this->collection->countDocuments(['_id' => 'user-idempotent']);
        self::assertSame(1, $count);
    }

    public function testItHandlesMultipleUsers(): void
    {
        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-1',
            email: 'first@example.com',
            occurredAt: new \DateTimeImmutable(),
        ));

        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-2',
            email: 'second@example.com',
            occurredAt: new \DateTimeImmutable(),
        ));

        $this->projection->onUserRegistered(new UserRegistered(
            id: 'user-3',
            email: 'third@example.com',
            occurredAt: new \DateTimeImmutable(),
        ));

        self::assertTrue($this->readModel->existsWithEmail(Email::fromString('first@example.com')));
        self::assertTrue($this->readModel->existsWithEmail(Email::fromString('second@example.com')));
        self::assertTrue($this->readModel->existsWithEmail(Email::fromString('third@example.com')));
        self::assertFalse($this->readModel->existsWithEmail(Email::fromString('nonexistent@example.com')));
    }

    public function testItStoresRegistrationTimestamp(): void
    {
        $registeredAt = new \DateTimeImmutable('2025-06-15T14:30:00+00:00');
        $event = new UserRegistered(
            id: 'user-timestamp',
            email: 'timestamp@example.com',
            occurredAt: $registeredAt,
        );

        $this->projection->onUserRegistered($event);

        $document = $this->collection->findOne(['_id' => 'user-timestamp']);
        self::assertNotNull($document);
        assert(is_array($document) || $document instanceof \ArrayAccess);
        self::assertArrayHasKey('registered_at', (array) $document);

        // Verify the timestamp is stored correctly
        $storedDate = $document['registered_at'];
        self::assertInstanceOf(UTCDateTime::class, $storedDate);
        self::assertSame(
            $registeredAt->format('Y-m-d H:i:s'),
            $storedDate->toDateTime()->format('Y-m-d H:i:s'),
        );
    }
}
