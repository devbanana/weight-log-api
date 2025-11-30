<?php

declare(strict_types=1);

namespace App\Domain\Common\EventStore;

/**
 * Thrown when an optimistic concurrency conflict is detected.
 *
 * This happens when two processes try to append events to the same
 * aggregate stream simultaneously.
 */
final class ConcurrencyException extends \RuntimeException
{
    public static function versionMismatch(
        string $aggregateId,
        string $aggregateType,
        int $expectedVersion,
        int $actualVersion,
    ): self {
        return new self(sprintf(
            'Concurrency conflict for %s[%s]: expected version %d, but current version is %d',
            $aggregateType,
            $aggregateId,
            $expectedVersion,
            $actualVersion,
        ));
    }
}
