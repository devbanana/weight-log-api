<?php

declare(strict_types=1);

namespace App\Domain\Common\Aggregate;

use App\Domain\Common\Event\DomainEventInterface;

/**
 * Contract for event-sourced aggregates.
 *
 * Event-sourced aggregates derive their state from a stream of domain events.
 * They can be reconstituted from an event stream and release new events
 * after state changes.
 */
interface EventSourcedAggregateInterface
{
    /**
     * Reconstitute an aggregate from an event stream.
     *
     * @param list<DomainEventInterface> $events
     */
    public static function reconstitute(array $events): static;

    /**
     * Release all recorded events and clear the internal list.
     *
     * @return list<DomainEventInterface>
     */
    public function releaseEvents(): array;
}
