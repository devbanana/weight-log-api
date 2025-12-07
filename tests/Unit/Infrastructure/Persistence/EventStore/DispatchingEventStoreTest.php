<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence\EventStore;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\Common\Exception\ConcurrencyException;
use App\Domain\User\Event\UserRegistered;
use App\Infrastructure\Persistence\EventStore\DispatchingEventStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(DispatchingEventStore::class)]
final class DispatchingEventStoreTest extends TestCase
{
    public function testItDelegatesToInnerEventStoreForAppend(): void
    {
        $aggregateId = 'user-123';
        $aggregateType = 'App\Domain\User\User';
        $event = new UserRegistered('user-123', 'test@example.com', 'hashed_password', new \DateTimeImmutable());
        $events = [$event];
        $expectedVersion = 0;

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('append')
            ->with($aggregateId, $aggregateType, $events, $expectedVersion)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->method('dispatch')
            ->willReturnCallback(static fn (object $event): Envelope => new Envelope($event))
        ;

        $store = new DispatchingEventStore($inner, $eventBus);

        $store->append($aggregateId, $aggregateType, $events, $expectedVersion);
    }

    public function testItDispatchesEachEventToMessageBusAfterSuccessfulAppend(): void
    {
        $aggregateId = 'user-123';
        $aggregateType = 'App\Domain\User\User';
        // Using same aggregate ID for both events (realistic for multi-event append)
        $event1 = new UserRegistered('user-123', 'test@example.com', 'hashed_password', new \DateTimeImmutable());
        $event2 = new UserRegistered('user-123', 'test@example.com', 'hashed_password', new \DateTimeImmutable());
        $events = [$event1, $event2];
        $expectedVersion = 0;

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('append')
            ->with($aggregateId, $aggregateType, $events, $expectedVersion)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(static function (DomainEventInterface $event) use ($event1, $event2): Envelope {
                self::assertContains($event, [$event1, $event2]);

                return new Envelope($event);
            })
        ;

        $store = new DispatchingEventStore($inner, $eventBus);

        $store->append($aggregateId, $aggregateType, $events, $expectedVersion);
    }

    public function testItDoesNotDispatchEventsIfInnerThrowsConcurrencyException(): void
    {
        $aggregateId = 'user-123';
        $aggregateType = 'App\Domain\User\User';
        $event = new UserRegistered('user-123', 'test@example.com', 'hashed_password', new \DateTimeImmutable());
        $events = [$event];
        $expectedVersion = 1;

        $concurrencyException = ConcurrencyException::versionMismatch(
            $aggregateId,
            $aggregateType,
            $expectedVersion,
            2,
        );

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('append')
            ->with($aggregateId, $aggregateType, $events, $expectedVersion)
            ->willThrowException($concurrencyException)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::never())
            ->method('dispatch')
        ;

        $store = new DispatchingEventStore($inner, $eventBus);

        $this->expectException(ConcurrencyException::class);

        $store->append($aggregateId, $aggregateType, $events, $expectedVersion);
    }

    public function testItDoesNotDispatchEventsIfInnerThrowsRuntimeException(): void
    {
        $aggregateId = 'user-123';
        $aggregateType = 'App\Domain\User\User';
        $event = new UserRegistered('user-123', 'test@example.com', 'hashed_password', new \DateTimeImmutable());
        $events = [$event];
        $expectedVersion = 0;

        $runtimeException = new \RuntimeException('Database connection failed');

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('append')
            ->with($aggregateId, $aggregateType, $events, $expectedVersion)
            ->willThrowException($runtimeException)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);
        $eventBus->expects(self::never())
            ->method('dispatch')
        ;

        $store = new DispatchingEventStore($inner, $eventBus);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $store->append($aggregateId, $aggregateType, $events, $expectedVersion);
    }

    public function testItDelegatesToInnerEventStoreForGetEvents(): void
    {
        $aggregateId = 'user-123';
        $aggregateType = 'App\Domain\User\User';
        $event1 = new UserRegistered('user-123', 'test@example.com', 'hashed_password', new \DateTimeImmutable());
        $event2 = new UserRegistered('user-123', 'updated@example.com', 'hashed_password', new \DateTimeImmutable());
        $expectedEvents = [$event1, $event2];

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('getEvents')
            ->with($aggregateId, $aggregateType)
            ->willReturn($expectedEvents)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);

        $store = new DispatchingEventStore($inner, $eventBus);

        $actualEvents = $store->getEvents($aggregateId, $aggregateType);

        self::assertSame($expectedEvents, $actualEvents);
    }

    public function testItDelegatesToInnerEventStoreForGetVersion(): void
    {
        $aggregateId = 'user-123';
        $aggregateType = 'App\Domain\User\User';
        $expectedVersion = 5;

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('getVersion')
            ->with($aggregateId, $aggregateType)
            ->willReturn($expectedVersion)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);

        $store = new DispatchingEventStore($inner, $eventBus);

        $actualVersion = $store->getVersion($aggregateId, $aggregateType);

        self::assertSame($expectedVersion, $actualVersion);
    }

    public function testItReturnsZeroVersionForNewAggregate(): void
    {
        $aggregateId = 'user-new';
        $aggregateType = 'App\Domain\User\User';

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('getVersion')
            ->with($aggregateId, $aggregateType)
            ->willReturn(0)
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);

        $store = new DispatchingEventStore($inner, $eventBus);

        $version = $store->getVersion($aggregateId, $aggregateType);

        self::assertSame(0, $version);
    }

    public function testItReturnsEmptyArrayForAggregateWithNoEvents(): void
    {
        $aggregateId = 'user-new';
        $aggregateType = 'App\Domain\User\User';

        $inner = $this->createMock(EventStoreInterface::class);
        $inner->expects(self::once())
            ->method('getEvents')
            ->with($aggregateId, $aggregateType)
            ->willReturn([])
        ;

        $eventBus = $this->createMock(MessageBusInterface::class);

        $store = new DispatchingEventStore($inner, $eventBus);

        $events = $store->getEvents($aggregateId, $aggregateType);

        self::assertSame([], $events);
    }
}
