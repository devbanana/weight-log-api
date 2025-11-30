<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\EventStore;

use App\Domain\Common\EventStore\EventStoreInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Decorator that dispatches events to Symfony Messenger after appending to the inner store.
 *
 * Consistency model: Events are persisted first, then dispatched. If dispatch fails after
 * a successful append, events will be in the store but handlers (projections) won't run.
 * This is acceptable because:
 * - Projections are idempotent (use upsert)
 * - The event store is the source of truth
 * - Projections can be rebuilt by replaying events if needed
 */
final readonly class DispatchingEventStore implements EventStoreInterface
{
    public function __construct(
        private EventStoreInterface $inner,
        private MessageBusInterface $eventBus,
    ) {
    }

    #[\Override]
    public function append(string $aggregateId, string $aggregateType, array $events, int $expectedVersion): void
    {
        $this->inner->append($aggregateId, $aggregateType, $events, $expectedVersion);

        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }

    #[\Override]
    public function getEvents(string $aggregateId, string $aggregateType): array
    {
        return $this->inner->getEvents($aggregateId, $aggregateType);
    }

    #[\Override]
    public function getVersion(string $aggregateId, string $aggregateType): int
    {
        return $this->inner->getVersion($aggregateId, $aggregateType);
    }
}
