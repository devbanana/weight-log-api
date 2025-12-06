<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\Common\EventStore\ConcurrencyException;
use App\Domain\Common\EventStore\EventStoreInterface;

/**
 * In-memory implementation of EventStore for testing.
 *
 * @internal Only to be used in tests
 */
final class InMemoryEventStore implements EventStoreInterface
{
    /**
     * @var array<string, list<DomainEventInterface>>
     */
    private array $eventStreams = [];

    /**
     * @var array<string, int>
     */
    private array $versions = [];

    /**
     * @var list<callable(DomainEventInterface): void>
     */
    private array $eventListeners = [];

    /**
     * Register a listener that will be called for each event when appended.
     *
     * @param callable(DomainEventInterface): void $listener
     */
    public function addListener(callable $listener): void
    {
        $this->eventListeners[] = $listener;
    }

    /**
     * @param non-empty-list<DomainEventInterface> $events
     */
    #[\Override]
    public function append(
        string $aggregateId,
        string $aggregateType,
        array $events,
        int $expectedVersion,
    ): void {
        foreach ($events as $event) {
            if ($event->id !== $aggregateId) {
                throw new \InvalidArgumentException(
                    sprintf('Event ID "%s" does not match aggregate ID "%s"', $event->id, $aggregateId),
                );
            }
        }

        $currentVersion = $this->getVersion($aggregateId, $aggregateType);

        if ($currentVersion !== $expectedVersion) {
            throw ConcurrencyException::versionMismatch(
                $aggregateId,
                $aggregateType,
                $expectedVersion,
                $currentVersion,
            );
        }

        $key = self::key($aggregateId, $aggregateType);

        if (!isset($this->eventStreams[$key])) {
            $this->eventStreams[$key] = [];
            $this->versions[$key] = 0;
        }

        foreach ($events as $event) {
            $this->eventStreams[$key][] = $event;
            ++$this->versions[$key];

            // Dispatch to listeners (sync projection updates)
            foreach ($this->eventListeners as $listener) {
                $listener($event);
            }
        }
    }

    #[\Override]
    public function getEvents(string $aggregateId, string $aggregateType): array
    {
        $key = self::key($aggregateId, $aggregateType);

        return $this->eventStreams[$key] ?? [];
    }

    #[\Override]
    public function getVersion(string $aggregateId, string $aggregateType): int
    {
        $key = self::key($aggregateId, $aggregateType);

        return $this->versions[$key] ?? 0;
    }

    private static function key(string $aggregateId, string $aggregateType): string
    {
        return $aggregateType . ':' . $aggregateId;
    }
}
