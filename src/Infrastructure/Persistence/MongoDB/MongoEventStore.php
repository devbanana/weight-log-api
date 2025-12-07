<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB;

use App\Domain\Common\Event\DomainEventInterface;
use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\Common\Exception\ConcurrencyException;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * MongoDB implementation of the event store.
 */
final readonly class MongoEventStore implements EventStoreInterface
{
    public function __construct(
        private Collection $collection,
        private DenormalizerInterface&NormalizerInterface $serializer,
    ) {
    }

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

        $version = $expectedVersion;

        foreach ($events as $event) {
            ++$version;

            $eventData = $this->serializer->normalize($event);
            assert(is_array($eventData));

            $this->collection->insertOne([
                'aggregate_id' => $aggregateId,
                'aggregate_type' => $aggregateType,
                'event_type' => $event::class,
                'event_data' => $eventData,
                'version' => $version,
                'occurred_at' => new UTCDateTime($event->occurredAt),
            ]);
        }
    }

    #[\Override]
    public function getEvents(string $aggregateId, string $aggregateType): array
    {
        $cursor = $this->collection->find(
            [
                'aggregate_id' => $aggregateId,
                'aggregate_type' => $aggregateType,
            ],
            ['sort' => ['version' => 1]],
        );

        $events = [];

        foreach ($cursor as $document) {
            assert($document instanceof BSONDocument);
            $eventType = $document['event_type'];
            $eventDataDoc = $document['event_data'];
            assert(is_string($eventType) && $eventDataDoc instanceof BSONDocument);

            $eventData = $eventDataDoc->getArrayCopy();
            $event = $this->serializer->denormalize($eventData, $eventType);
            assert($event instanceof DomainEventInterface);

            $events[] = $event;
        }

        return $events;
    }

    #[\Override]
    public function getVersion(string $aggregateId, string $aggregateType): int
    {
        return $this->collection->countDocuments([
            'aggregate_id' => $aggregateId,
            'aggregate_type' => $aggregateType,
        ]);
    }
}
