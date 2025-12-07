<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Clock;

use App\Application\Clock\ClockInterface;
use App\Infrastructure\Clock\SystemClock;
use App\Tests\UseCase\FrozenClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for Clock implementations.
 *
 * All implementations of ClockInterface must pass these tests
 * to ensure consistent behavior across adapters.
 *
 * @internal
 */
#[CoversClass(SystemClock::class)]
final class ClockContractTest extends TestCase
{
    #[DataProvider('clockProvider')]
    public function testItReturnsDateTimeImmutable(ClockInterface $clock): void
    {
        $now = $clock->now();

        self::assertInstanceOf(\DateTimeImmutable::class, $now);
    }

    #[DataProvider('clockProvider')]
    public function testItReturnsUtcTimezone(ClockInterface $clock): void
    {
        $now = $clock->now();

        self::assertSame('UTC', $now->getTimezone()->getName());
    }

    /**
     * @return \Generator<string, array{ClockInterface}>
     */
    public static function clockProvider(): iterable
    {
        // FrozenClock serves as reference implementation, validates test correctness,
        // and ensures the test double used in Behat use case tests behaves correctly
        yield 'FrozenClock' => [new FrozenClock()];

        yield 'SystemClock' => [new SystemClock()];
    }
}
