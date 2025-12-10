<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Application\Clock\ClockInterface;

/**
 * System clock implementation that returns the actual current time.
 */
final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
    }
}
