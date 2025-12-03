<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\User\Event\UserRegistered;
use App\Domain\User\User;
use App\Infrastructure\Persistence\MongoDB\MongoEventStore;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * MongoDB-specific tests for MongoEventStore.
 *
 * These tests verify MongoDB-specific implementation details that are
 * not part of the EventStoreInterface contract, such as BSON document
 * structure and date storage format.
 *
 * For contract tests that verify interface behavior across all
 * implementations, see EventStoreContractTest.
 *
 * @covers \App\Infrastructure\Persistence\MongoDB\MongoEventStore
 *
 * @internal
 */
final class MongoEventStoreTest extends TestCase
{
    private const string AGGREGATE_TYPE = User::class;

    /**
     * Verify the raw document structure stores occurred_at as BSON UTCDateTime.
     *
     * This ensures proper MongoDB date indexing and querying capabilities.
     */
    public function testItStoresOccurredAtAsBsonUtcDateTime(): void
    {
        $collection = self::createMongoCollection();
        $eventStore = self::createMongoEventStore($collection);

        $aggregateId = 'user-occurred-at-test';
        $specificTime = new \DateTimeImmutable('2025-06-15 14:30:45', new \DateTimeZone('UTC'));
        $event = new UserRegistered(
            id: $aggregateId,
            email: 'document@example.com',
            hashedPassword: 'hashed_password',
            occurredAt: $specificTime,
        );

        $eventStore->append($aggregateId, self::AGGREGATE_TYPE, [$event], expectedVersion: 0);

        // Query the raw document directly to verify BSON structure
        $document = $collection->findOne([
            'aggregate_id' => $aggregateId,
            'aggregate_type' => self::AGGREGATE_TYPE,
        ]);

        self::assertNotNull($document, 'Document should exist in collection');
        self::assertInstanceOf(BSONDocument::class, $document);
        self::assertArrayHasKey('occurred_at', $document);

        $occurredAt = $document['occurred_at'];
        self::assertInstanceOf(
            UTCDateTime::class,
            $occurredAt,
            'occurred_at should be stored as BSON UTCDateTime',
        );

        // Verify the timestamp value matches
        $storedDateTime = $occurredAt->toDateTime();
        self::assertSame(
            $specificTime->format('Y-m-d H:i:s'),
            $storedDateTime->format('Y-m-d H:i:s'),
        );
    }

    private static function createMongoCollection(): Collection
    {
        $mongoUrl = $_ENV['MONGODB_URL'];
        self::assertIsString($mongoUrl, 'MONGODB_URL must be set in environment for tests');
        $database = $_ENV['MONGODB_DATABASE'];
        self::assertIsString($database, 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);
        $collection = $client->selectCollection($database, 'events');
        $collection->drop();

        return $collection;
    }

    private static function createMongoEventStore(Collection $collection): MongoEventStore
    {
        $serializer = new Serializer([
            new DateTimeNormalizer(),
            new ObjectNormalizer(),
        ]);

        return new MongoEventStore($collection, $serializer);
    }
}
