<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use MongoDB\Model\BSONDocument;

/**
 * Helper trait for MongoDB integration tests.
 *
 * Provides utilities for collection setup and document assertions.
 */
trait MongoHelper
{
    private static function getMongoDatabase(): Database
    {
        $mongoUrl = $_ENV['MONGODB_URL'];
        self::assertIsString($mongoUrl, 'MONGODB_URL must be set in environment for tests');

        $databaseName = $_ENV['MONGODB_DATABASE'];
        self::assertIsString($databaseName, 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);

        return $client->selectDatabase($databaseName);
    }

    private static function findDocument(Collection $collection, string $id): BSONDocument
    {
        $document = $collection->findOne(['_id' => $id]);
        self::assertNotNull($document, "Document with ID '{$id}' not found");
        self::assertInstanceOf(BSONDocument::class, $document);

        return $document;
    }

    private static function assertDateTimeEquals(
        \DateTimeImmutable $expected,
        UTCDateTime $actual,
        string $message = 'DateTime values do not match',
    ): void {
        self::assertSame(
            $expected->format('Y-m-d H:i:s'),
            $actual->toDateTime()->format('Y-m-d H:i:s'),
            $message,
        );
    }
}
