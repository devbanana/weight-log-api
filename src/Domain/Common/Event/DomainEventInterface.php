<?php

declare(strict_types=1);

namespace App\Domain\Common\Event;

/**
 * Marker interface for all domain events.
 *
 * Domain events represent something that happened in the domain.
 * They are immutable and named in past tense (e.g., UserWasRegistered).
 */
interface DomainEventInterface
{
    /**
     * The unique identifier of the aggregate that produced this event.
     */
    public string $id { get; }

    /**
     * When this event occurred.
     */
    public \DateTimeImmutable $occurredAt { get; }
}
