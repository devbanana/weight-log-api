<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Projection;

use MongoDB\BSON\UTCDateTime;
use MongoDB\Model\BSONDocument;

/**
 * Helper trait for tests that verify MongoDB document state.
 */
trait MongoHelper
{
    private function findDocument(string $id): BSONDocument
    {
        $document = $this->collection->findOne(['_id' => $id]);
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
