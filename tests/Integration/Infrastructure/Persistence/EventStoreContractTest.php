<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\Common\EventStore\ConcurrencyException;
use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\User\Event\UserWasRegistered;
use App\Domain\User\User;
use App\Infrastructure\Persistence\MongoDB\MongoEventStore;
use App\Tests\UseCase\InMemoryEventStore;
use MongoDB\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Contract tests for EventStore implementations.
 *
 * All implementations of EventStoreInterface must pass these tests
 * to ensure consistent behavior across adapters.
 *
 * @covers \App\Infrastructure\Persistence\MongoDB\MongoEventStore
 * @covers \App\Tests\UseCase\InMemoryEventStore
 *
 * @internal
 */
final class EventStoreContractTest extends TestCase
{
    private const string AGGREGATE_TYPE = User::class;

    #[DataProvider('eventStoreProvider')]
    public function testItAppendsEventsToNewAggregate(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-123';
        $event = $this->createEvent($aggregateId);

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event], expectedVersion: 0);

        $storedEvents = $eventStore->getEvents($aggregateId, self::AGGREGATE_TYPE);
        self::assertCount(1, $storedEvents);
        self::assertSame($event->id, $storedEvents[0]->id);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItRetrievesAllEventsForAggregate(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-456';
        $event1 = $this->createEvent($aggregateId, 'first@example.com');
        $event2 = $this->createEvent($aggregateId, 'second@example.com');

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event1], expectedVersion: 0);
        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event2], expectedVersion: 1);

        $storedEvents = $eventStore->getEvents($aggregateId, self::AGGREGATE_TYPE);
        self::assertCount(2, $storedEvents);
        self::assertInstanceOf(UserWasRegistered::class, $storedEvents[0]);
        self::assertInstanceOf(UserWasRegistered::class, $storedEvents[1]);
        self::assertSame('first@example.com', $storedEvents[0]->email);
        self::assertSame('second@example.com', $storedEvents[1]->email);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItAppendsMultipleEventsAtOnce(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-789';
        $event1 = $this->createEvent($aggregateId, 'one@example.com');
        $event2 = $this->createEvent($aggregateId, 'two@example.com');

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event1, $event2], expectedVersion: 0);

        self::assertSame(2, $eventStore->getVersion($aggregateId, self::AGGREGATE_TYPE));
        $storedEvents = $eventStore->getEvents($aggregateId, self::AGGREGATE_TYPE);
        self::assertCount(2, $storedEvents);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItTracksVersionCorrectly(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-version-test';

        self::assertSame(0, $eventStore->getVersion($aggregateId, self::AGGREGATE_TYPE));

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$this->createEvent($aggregateId)], expectedVersion: 0);
        self::assertSame(1, $eventStore->getVersion($aggregateId, self::AGGREGATE_TYPE));

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$this->createEvent($aggregateId)], expectedVersion: 1);
        self::assertSame(2, $eventStore->getVersion($aggregateId, self::AGGREGATE_TYPE));
    }

    #[DataProvider('eventStoreProvider')]
    public function testItThrowsConcurrencyExceptionOnVersionMismatch(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-concurrency-test';

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$this->createEvent($aggregateId)], expectedVersion: 0);

        $this->expectException(ConcurrencyException::class);

        // Try to append with wrong expected version (0 instead of 1)
        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$this->createEvent($aggregateId)], expectedVersion: 0);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItThrowsConcurrencyExceptionWhenExpectedVersionTooHigh(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-high-version';

        $this->expectException(ConcurrencyException::class);

        // Try to append with expected version 5 when no events exist (version is 0)
        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$this->createEvent($aggregateId)], expectedVersion: 5);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItReturnsEmptyArrayForNonExistentAggregate(EventStoreInterface $eventStore): void
    {
        $events = $eventStore->getEvents('non-existent-id', self::AGGREGATE_TYPE);

        self::assertSame([], $events);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItReturnsZeroVersionForNonExistentAggregate(EventStoreInterface $eventStore): void
    {
        $version = $eventStore->getVersion('non-existent-id', self::AGGREGATE_TYPE);

        self::assertSame(0, $version);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItIsolatesEventsByAggregateType(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'shared-id';
        $userEvent = $this->createEvent($aggregateId, 'user@example.com');

        $eventStore->append($aggregateId, 'User', [$userEvent], expectedVersion: 0);

        // Same ID but different type should have no events
        self::assertSame([], $eventStore->getEvents($aggregateId, 'Order'));
        self::assertSame(0, $eventStore->getVersion($aggregateId, 'Order'));
    }

    #[DataProvider('eventStoreProvider')]
    public function testItIsolatesEventsByAggregateId(EventStoreInterface $eventStore): void
    {
        $event1 = $this->createEvent('user-1', 'user1@example.com');
        $event2 = $this->createEvent('user-2', 'user2@example.com');

        $eventStore->append('user-1', self::AGGREGATE_TYPE, [$event1], expectedVersion: 0);
        $eventStore->append('user-2', self::AGGREGATE_TYPE, [$event2], expectedVersion: 0);

        $eventsForUser1 = $eventStore->getEvents('user-1', self::AGGREGATE_TYPE);
        $eventsForUser2 = $eventStore->getEvents('user-2', self::AGGREGATE_TYPE);

        self::assertCount(1, $eventsForUser1);
        self::assertCount(1, $eventsForUser2);
        self::assertInstanceOf(UserWasRegistered::class, $eventsForUser1[0]);
        self::assertInstanceOf(UserWasRegistered::class, $eventsForUser2[0]);
        self::assertSame('user1@example.com', $eventsForUser1[0]->email);
        self::assertSame('user2@example.com', $eventsForUser2[0]->email);
    }

    #[DataProvider('eventStoreProvider')]
    public function testItPreservesDateTimeImmutablePrecision(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-datetime-test';
        $specificTime = new \DateTimeImmutable('2025-06-15 14:30:45', new \DateTimeZone('UTC'));
        $event = new UserWasRegistered(
            id: $aggregateId,
            email: 'datetime@example.com',
            occurredAt: $specificTime,
        );

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event], expectedVersion: 0);

        $storedEvents = $eventStore->getEvents($aggregateId, self::AGGREGATE_TYPE);

        self::assertCount(1, $storedEvents);
        self::assertInstanceOf(\DateTimeImmutable::class, $storedEvents[0]->occurredAt);
        self::assertSame(
            $specificTime->format(\DateTimeInterface::ATOM),
            $storedEvents[0]->occurredAt->format(\DateTimeInterface::ATOM),
        );
    }

    #[DataProvider('eventStoreProvider')]
    public function testItReturnsEventsInVersionOrder(EventStoreInterface $eventStore): void
    {
        $aggregateId = 'user-order-test';
        $event1 = $this->createEvent($aggregateId, 'first@example.com');
        $event2 = $this->createEvent($aggregateId, 'second@example.com');
        $event3 = $this->createEvent($aggregateId, 'third@example.com');

        // Append all at once to ensure ordering is by version, not insertion
        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event1, $event2, $event3], expectedVersion: 0);

        $storedEvents = $eventStore->getEvents($aggregateId, self::AGGREGATE_TYPE);

        self::assertCount(3, $storedEvents);
        self::assertInstanceOf(UserWasRegistered::class, $storedEvents[0]);
        self::assertInstanceOf(UserWasRegistered::class, $storedEvents[1]);
        self::assertInstanceOf(UserWasRegistered::class, $storedEvents[2]);
        self::assertSame('first@example.com', $storedEvents[0]->email);
        self::assertSame('second@example.com', $storedEvents[1]->email);
        self::assertSame('third@example.com', $storedEvents[2]->email);
    }

    /**
     * @return \Generator<string, array{EventStoreInterface}>
     */
    public static function eventStoreProvider(): iterable
    {
        // InMemory serves as reference implementation, validates test correctness,
        // and ensures the test double used in Behat use case tests behaves correctly
        yield 'InMemory' => [new InMemoryEventStore()];

        yield 'MongoDB' => [self::createMongoEventStore()];
    }

    private static function createMongoEventStore(): MongoEventStore
    {
        $mongoUrl = $_ENV['MONGODB_URL'];
        self::assertIsString($mongoUrl, 'MONGODB_URL must be set in environment for tests');
        $database = $_ENV['MONGODB_DATABASE'];
        self::assertIsString($database, 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);
        $collection = $client->selectCollection($database, 'events');
        $collection->drop();

        $serializer = new Serializer([
            new DateTimeNormalizer(),
            new ObjectNormalizer(),
        ]);

        return new MongoEventStore($collection, $serializer);
    }

    private function createEvent(string $aggregateId, string $email = 'test@example.com'): DomainEventInterface
    {
        return new UserWasRegistered(
            id: $aggregateId,
            email: $email,
            occurredAt: new \DateTimeImmutable('2025-01-01 12:00:00'),
        );
    }
}
