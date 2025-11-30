<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use App\Domain\Common\Aggregate\EventSourcedAggregateInterface;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

/**
 * PHPStan extension that marks properties in event-sourced aggregates as "always read".
 *
 * Criterion: Classes implementing EventSourcedAggregateInterface are event-sourced
 * aggregates. Their private properties represent state derived from domain events and will
 * be read when behavior is implemented.
 *
 * This prevents false positives for "property written but never read" in aggregates
 * that are being developed incrementally.
 */
final class EventSourcedAggregatePropertiesExtension implements ReadWritePropertiesExtension
{
    #[\Override]
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return $property->getDeclaringClass()->implementsInterface(EventSourcedAggregateInterface::class);
    }

    #[\Override]
    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    #[\Override]
    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }
}
