<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\Clock\ClockInterface;

/**
 * Test clock that returns a fixed time.
 *
 * @internal Only to be used in tests
 */
final class FrozenClock implements ClockInterface
{
    private \DateTimeImmutable $frozenTime;

    public function __construct(?\DateTimeImmutable $frozenTime = null)
    {
        $this->frozenTime = $frozenTime ?? new \DateTimeImmutable('2025-01-15 12:00:00', new \DateTimeZone('UTC'));
    }

    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return $this->frozenTime;
    }
}
