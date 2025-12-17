<?php

declare(strict_types=1);

namespace App\Infrastructure\DependencyInjection;

use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Validates that the Clock service is configured with UTC timezone.
 *
 * All timestamps in this application must be stored in UTC.
 * This compiler pass ensures the clock cannot be misconfigured.
 */
final class ClockTimezoneValidatorPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasAlias(ClockInterface::class)) {
            return;
        }

        $aliasId = (string) $container->getAlias(ClockInterface::class);
        $definition = $container->findDefinition($aliasId);
        $class = $definition->getClass();
        $arguments = $definition->getArguments();

        // MockClock takes timezone as second argument, NativeClock as first
        $timezone = $class === MockClock::class
            ? ($arguments[1] ?? null)
            : ($arguments[0] ?? null);

        if ($timezone !== 'UTC') {
            $timezoneDisplay = is_string($timezone)
                ? sprintf('"%s"', $timezone)
                : 'null';

            throw new \InvalidArgumentException(
                sprintf(
                    'Clock must be configured with UTC timezone, got %s. '
                    . 'All timestamps in this application must be stored in UTC.',
                    $timezoneDisplay,
                ),
            );
        }
    }
}
