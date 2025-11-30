<?php

declare(strict_types=1);

namespace App\Domain\Common\Aggregate;

use App\Domain\Common\Event\DomainEventInterface;

/**
 * Trait for aggregates that record domain events.
 *
 * Provides the infrastructure for event sourcing:
 * - Recording events (which also applies them to update state)
 * - Releasing events for persistence
 *
 * Aggregates using this trait must implement their own apply() method
 * to handle event application in a type-safe way.
 */
trait RecordsEvents
{
    /**
     * @var list<DomainEventInterface>
     */
    private array $recordedEvents = [];

    /**
     * Release all recorded events and clear the internal list.
     *
     * @return list<DomainEventInterface>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    /**
     * Record an event and apply it to update internal state.
     */
    private function recordThat(DomainEventInterface $event): void
    {
        $this->apply($event);
        $this->recordedEvents[] = $event;
    }

    /**
     * Apply an event to update internal state.
     *
     * Each aggregate must implement this method with a match expression
     * to handle its specific event types in a type-safe way.
     */
    abstract private function apply(DomainEventInterface $event): void;
}
