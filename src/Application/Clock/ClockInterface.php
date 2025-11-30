<?php

declare(strict_types=1);

namespace App\Application\Clock;

/**
 * Port for accessing the current time.
 *
 * Using an interface allows the application layer to remain
 * decoupled from system time, making it testable with frozen clocks.
 */
interface ClockInterface
{
    /**
     * Get the current time in UTC.
     */
    public function now(): \DateTimeImmutable;
}
