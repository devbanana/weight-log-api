<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure;

use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\Common\Exception\ConcurrencyException;
use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\User;
use App\Infrastructure\Persistence\EventStore\DispatchingEventStore;
use App\Infrastructure\Persistence\MongoDB\MongoEventStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

/**
 * Integration test that verifies the DispatchingEventStore dispatches events to the message bus.
 *
 * Uses Symfony Messenger's in-memory transport to capture dispatched events
 * without depending on projections or read models.
 *
 * @internal
 */
#[CoversClass(DispatchingEventStore::class)]
#[UsesClass(MongoEventStore::class)]
final class EventDispatchIntegrationTest extends KernelTestCase
{
    use MongoHelper;

    private EventStoreInterface $eventStore;
    private InMemoryTransport $eventTransport;

    #[\Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $this->eventStore = $container->get(EventStoreInterface::class);

        $transport = $container->get('messenger.transport.events');
        self::assertInstanceOf(InMemoryTransport::class, $transport);
        $this->eventTransport = $transport;

        // Clean up MongoDB events collection
        self::getMongoDatabase()->selectCollection('events')->drop();

        // Reset the in-memory transport
        $this->eventTransport->reset();
    }

    public function testItDispatchesEventToMessageBusWhenAppendingToEventStore(): void
    {
        $userId = 'user-dispatch-test-1';
        $email = 'integration@example.com';
        $occurredAt = new \DateTimeImmutable('2025-01-20T15:45:00+00:00');

        $event = new UserRegistered(
            id: $userId,
            email: $email,
            dateOfBirth: '1990-05-15',
            displayName: 'Test User',
            hashedPassword: 'hashed_password',
            occurredAt: $occurredAt,
        );

        $this->eventStore->append($userId, User::class, [$event], expectedVersion: 0);

        // Verify event was persisted to event store
        $storedEvents = $this->eventStore->getEvents($userId, User::class);
        self::assertCount(1, $storedEvents);

        // Verify event was dispatched to the message bus
        $dispatchedEnvelopes = $this->eventTransport->getSent();
        self::assertCount(1, $dispatchedEnvelopes);

        $dispatchedEvent = $dispatchedEnvelopes[0]->getMessage();
        self::assertInstanceOf(UserRegistered::class, $dispatchedEvent);
        self::assertSame($email, $dispatchedEvent->email);
        self::assertSame($userId, $dispatchedEvent->id);
    }

    public function testItDispatchesMultipleEventsFromSeparateAppends(): void
    {
        $userId1 = 'user-dispatch-test-2';
        $userId2 = 'user-dispatch-test-3';

        $event1 = new UserRegistered(
            id: $userId1,
            email: 'first@example.com',
            dateOfBirth: '1990-05-15',
            displayName: 'First User',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );
        $this->eventStore->append($userId1, User::class, [$event1], expectedVersion: 0);

        $event2 = new UserRegistered(
            id: $userId2,
            email: 'second@example.com',
            dateOfBirth: '1990-05-15',
            displayName: 'Second User',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );
        $this->eventStore->append($userId2, User::class, [$event2], expectedVersion: 0);

        // Verify both events were dispatched
        $dispatchedEnvelopes = $this->eventTransport->getSent();
        self::assertCount(2, $dispatchedEnvelopes);

        $dispatchedEmails = array_map(
            static function (Envelope $envelope): string {
                $message = $envelope->getMessage();
                self::assertInstanceOf(UserRegistered::class, $message);

                return $message->email;
            },
            $dispatchedEnvelopes,
        );
        self::assertContains('first@example.com', $dispatchedEmails);
        self::assertContains('second@example.com', $dispatchedEmails);
    }

    public function testItDispatchesAllEventsFromSingleAppend(): void
    {
        $userId = 'user-dispatch-test-4';
        $occurredAt = new \DateTimeImmutable('2025-01-20T16:00:00+00:00');

        $event1 = new UserRegistered(
            id: $userId,
            email: 'multi@example.com',
            dateOfBirth: '1990-05-15',
            displayName: 'Test User',
            hashedPassword: 'hashed_password',
            occurredAt: $occurredAt,
        );

        $event2 = new UserLoggedIn(
            id: $userId,
            occurredAt: $occurredAt->modify('+1 second'),
        );

        // Append both events at once
        $this->eventStore->append($userId, User::class, [$event1, $event2], expectedVersion: 0);

        // Verify both events were dispatched
        $dispatchedEnvelopes = $this->eventTransport->getSent();
        self::assertCount(2, $dispatchedEnvelopes);
    }

    public function testItDoesNotDispatchEventsWhenPersistenceFails(): void
    {
        $userId = 'user-dispatch-test-5';

        // First append - should succeed
        $event1 = new UserRegistered(
            id: $userId,
            email: 'concurrency@example.com',
            dateOfBirth: '1990-05-15',
            displayName: 'Concurrency User',
            hashedPassword: 'hashed_password',
            occurredAt: new \DateTimeImmutable(),
        );
        $this->eventStore->append($userId, User::class, [$event1], expectedVersion: 0);

        // Reset transport to only capture subsequent dispatches
        $this->eventTransport->reset();

        // Try to append login event with wrong version - should throw ConcurrencyException
        $loginEvent = new UserLoggedIn(
            id: $userId,
            occurredAt: new \DateTimeImmutable(),
        );

        try {
            // This should throw because we're using version 0 when version is actually 1
            $this->eventStore->append($userId, User::class, [$loginEvent], expectedVersion: 0);
            self::fail('Expected ConcurrencyException to be thrown');
        } catch (ConcurrencyException) {
            // Expected - concurrency violation due to wrong version
        }

        // No events should have been dispatched after the failed append
        self::assertCount(0, $this->eventTransport->getSent());
    }
}
