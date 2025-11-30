<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

/**
 * Marks methods in domain interfaces as "used" for dead-code detection.
 *
 * Interface methods are contracts - they're "used" by virtue of being implemented.
 * This prevents false positives for interface methods in the domain layer.
 */
final class DomainInterfaceMethodUsageProvider extends ReflectionBasedMemberUsageProvider
{
    private const string DOMAIN_NAMESPACE = 'App\Domain\\';

    #[\Override]
    public function shouldMarkMethodAsUsed(\ReflectionMethod $method): ?VirtualUsageData
    {
        $declaringClass = $method->getDeclaringClass();

        // Only apply to interfaces in the domain namespace
        if (!$declaringClass->isInterface()) {
            return null;
        }

        if (!str_starts_with($declaringClass->getName(), self::DOMAIN_NAMESPACE)) {
            return null;
        }

        return VirtualUsageData::withNote('Domain interface method (contract)');
    }
}
