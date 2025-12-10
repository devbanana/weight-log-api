<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\DependencyInjection;

use App\Infrastructure\DependencyInjection\ClockTimezoneValidatorPass;
use App\Infrastructure\Kernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(ClockTimezoneValidatorPass::class)]
#[CoversClass(Kernel::class)]
final class ClockTimezoneValidatorPassTest extends TestCase
{
    public function testItPassesWhenClockIsConfiguredWithUtc(): void
    {
        $container = self::createContainerWithTimezone('UTC');

        $pass = new ClockTimezoneValidatorPass();
        $pass->process($container);

        // If we get here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testItThrowsWhenClockIsConfiguredWithNonUtcTimezone(): void
    {
        $container = self::createContainerWithTimezone('America/New_York');

        $pass = new ClockTimezoneValidatorPass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clock must be configured with UTC timezone, got "America/New_York"');

        $pass->process($container);
    }

    public function testItThrowsWhenClockIsConfiguredWithNullTimezone(): void
    {
        $container = self::createContainerWithTimezone(null);

        $pass = new ClockTimezoneValidatorPass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clock must be configured with UTC timezone, got null');

        $pass->process($container);
    }

    public function testItThrowsWhenClockHasNoTimezoneArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register(NativeClock::class, NativeClock::class);
        $container->setAlias(ClockInterface::class, NativeClock::class);

        $pass = new ClockTimezoneValidatorPass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clock must be configured with UTC timezone, got null');

        $pass->process($container);
    }

    public function testItSkipsValidationWhenClockInterfaceIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        $pass = new ClockTimezoneValidatorPass();
        $pass->process($container);

        // If we get here without exception, the test passes
        $this->addToAssertionCount(1);
    }

    public function testKernelFailsToBootWithNonUtcClock(): void
    {
        $kernel = new Kernel('invalid_clock_test', false);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Clock must be configured with UTC timezone');

        $kernel->boot();
    }

    private static function createContainerWithTimezone(?string $timezone): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(NativeClock::class, NativeClock::class)
            ->addArgument($timezone)
        ;
        $container->setAlias(ClockInterface::class, NativeClock::class);

        return $container;
    }
}
