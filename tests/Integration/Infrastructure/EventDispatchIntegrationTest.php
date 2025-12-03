<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure;

use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\User;
use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\Email;
use MongoDB\Client;
use MongoDB\Collection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test that verifies the complete event dispatching flow.
 *
 * Tests the full pipeline:
 * EventStore.append() → DispatchingEventStore → MessageBus → UserProjection → MongoUserReadModel
 *
 * This test validates that when events are appended to the event store,
 * they are automatically dispatched via Symfony Messenger and handled by
 * projections that update the read model.
 *
 * @covers \App\Infrastructure\Persistence\EventStore\DispatchingEventStore
 * @covers \App\Infrastructure\Projection\UserProjection
 *
 * @internal
 */
final class EventDispatchIntegrationTest extends KernelTestCase
{
    private EventStoreInterface $eventStore;
    private UserReadModelInterface $userReadModel;
    private Collection $eventsCollection;
    private Collection $usersCollection;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        // Get services from container
        $container = self::getContainer();
        $this->eventStore = $container->get(EventStoreInterface::class);
        $this->userReadModel = $container->get(UserReadModelInterface::class);

        // Clean up MongoDB collections before each test
        $mongoUrl = $_ENV['MONGODB_URL'];
        self::assertIsString($mongoUrl, 'MONGODB_URL must be set in environment for tests');
        $database = $_ENV['MONGODB_DATABASE'];
        self::assertIsString($database, 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);
        $this->eventsCollection = $client->selectCollection($database, 'events');
        $this->usersCollection = $client->selectCollection($database, 'users');

        $this->eventsCollection->drop();
        $this->usersCollection->drop();
    }

    public function testItDispatchesEventAndUpdatesReadModelWhenAppendingToEventStore(): void
    {
        $userId = 'user-dispatch-test-1';
        $email = 'integration@example.com';
        $occurredAt = new \DateTimeImmutable('2025-01-20T15:45:00+00:00');

        // Create and append event to event store
        $event = new UserRegistered(
            id: $userId,
            email: $email,
            hashedPassword: 'hashed_password',
            occurredAt: $occurredAt,
        );

        $this->eventStore->append($userId, User::class, [$event], expectedVersion: 0);

        // Verify event was persisted to event store
        $storedEvents = $this->eventStore->getEvents($userId, User::class);
        self::assertCount(1, $storedEvents);
        self::assertInstanceOf(UserRegistered::class, $storedEvents[0]);
        self::assertSame($email, $storedEvents[0]->email);

        // Verify read model was updated via projection
        // The DispatchingEventStore should have dispatched the event to Messenger,
        // which should have triggered UserProjection, which should have updated MongoUserReadModel
        self::assertTrue(
            $this->userReadModel->existsWithEmail(Email::fromString($email)),
            'Read model should be updated after event is appended to event store',
        );
    }

    public function testItUpdatesReadModelForMultipleEvents(): void
    {
        $userId1 = 'user-dispatch-test-2';
        $userId2 = 'user-dispatch-test-3';
        $email1 = 'first@example.com';
        $email2 = 'second@example.com';

        // Append first user event
        $event1 = new UserRegistered(
            id: $userId1,
            email: $email1,
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );
        $this->eventStore->append($userId1, User::class, [$event1], expectedVersion: 0);

        // Append second user event
        $event2 = new UserRegistered(
            id: $userId2,
            email: $email2,
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );
        $this->eventStore->append($userId2, User::class, [$event2], expectedVersion: 0);

        // Verify both users exist in read model
        self::assertTrue($this->userReadModel->existsWithEmail(Email::fromString($email1)));
        self::assertTrue($this->userReadModel->existsWithEmail(Email::fromString($email2)));
    }

    public function testItHandlesMultipleEventsInSingleAppend(): void
    {
        $userId = 'user-dispatch-test-4';
        $initialEmail = 'initial@example.com';
        $occurredAt = new \DateTimeImmutable('2025-01-20T16:00:00+00:00');

        // Create multiple events for the same aggregate
        // (In real scenario, this might be registration + email verification)
        $event1 = new UserRegistered(
            id: $userId,
            email: $initialEmail,
            hashedPassword: 'hashed_password',
            occurredAt: $occurredAt,
        );

        // For this test, we'll use a second UserRegistered event to simulate
        // multiple events being appended at once (even though in production
        // you wouldn't register the same user twice)
        $event2 = new UserRegistered(
            id: $userId,
            email: $initialEmail,
            hashedPassword: 'hashed_password',
            occurredAt: $occurredAt->modify('+1 second'),
        );

        // Append both events at once
        $this->eventStore->append($userId, User::class, [$event1, $event2], expectedVersion: 0);

        // Verify both events were persisted
        $storedEvents = $this->eventStore->getEvents($userId, User::class);
        self::assertCount(2, $storedEvents);

        // Verify read model was updated (projection is idempotent, so duplicate is fine)
        self::assertTrue($this->userReadModel->existsWithEmail(Email::fromString($initialEmail)));
    }

    public function testItMaintainsReadModelDataIntegrity(): void
    {
        $userId = 'user-dispatch-test-5';
        $email = 'integrity@example.com';
        $registeredAt = new \DateTimeImmutable('2025-01-20T17:30:00+00:00');

        $event = new UserRegistered(
            id: $userId,
            email: $email,
            hashedPassword: 'hashed_password',
            occurredAt: $registeredAt,
        );

        $this->eventStore->append($userId, User::class, [$event], expectedVersion: 0);

        // Verify the document structure in the read model
        $document = $this->usersCollection->findOne(['_id' => $userId]);
        self::assertNotNull($document, 'User document should exist in read model collection');
        assert($document instanceof \ArrayAccess);
        self::assertSame($email, $document['email']);
        self::assertArrayHasKey('registered_at', (array) $document);

        // Verify read model query works correctly
        self::assertTrue($this->userReadModel->existsWithEmail(Email::fromString($email)));
        self::assertFalse($this->userReadModel->existsWithEmail(Email::fromString('nonexistent@example.com')));
    }

    public function testItDoesNotUpdateReadModelWhenEventStoreThrowsException(): void
    {
        $userId = 'user-dispatch-test-6';
        $email = 'concurrency@example.com';

        // First append - should succeed
        $event1 = new UserRegistered(
            id: $userId,
            email: $email,
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );
        $this->eventStore->append($userId, User::class, [$event1], expectedVersion: 0);

        // Verify initial state
        self::assertTrue($this->userReadModel->existsWithEmail(Email::fromString($email)));

        // Try to append with wrong version - should throw ConcurrencyException
        $event2 = new UserRegistered(
            id: $userId,
            email: 'updated@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );

        try {
            // This should throw because we're using version 0 when version is actually 1
            $this->eventStore->append($userId, User::class, [$event2], expectedVersion: 0);
            self::fail('Expected ConcurrencyException to be thrown');
        } catch (\Exception $e) {
            // Expected - exception should be thrown
        }

        // Read model should still have original email, not updated email
        self::assertTrue($this->userReadModel->existsWithEmail(Email::fromString($email)));
        self::assertFalse($this->userReadModel->existsWithEmail(Email::fromString('updated@example.com')));
    }
}
