<?php

declare(strict_types=1);

namespace App\Domain\Common\EventStore;

use App\Domain\Common\Event\DomainEventInterface;

/**
 * Port for event storage.
 *
 * The event store is the source of truth for all aggregate state.
 * Events are appended atomically with optimistic concurrency control.
 */
interface EventStoreInterface
{
    /**
     * Append events to an aggregate's event stream.
     *
     * @param non-empty-list<DomainEventInterface> $events          Events to append (must not be empty)
     * @param int                                  $expectedVersion The expected current version (for optimistic concurrency)
     *
     * @throws ConcurrencyException If expectedVersion doesn't match current version
     */
    public function append(
        string $aggregateId,
        string $aggregateType,
        array $events,
        int $expectedVersion,
    ): void;

    /**
     * Get all events for an aggregate.
     *
     * @return list<DomainEventInterface>
     */
    public function getEvents(string $aggregateId, string $aggregateType): array;

    /**
     * Get the current version of an aggregate's event stream.
     *
     * Returns 0 if no events exist for this aggregate.
     */
    public function getVersion(string $aggregateId, string $aggregateType): int;
}
