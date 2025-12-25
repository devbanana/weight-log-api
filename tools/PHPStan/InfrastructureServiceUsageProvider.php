<?php

declare(strict_types=1);

namespace Tools\PHPStan;

use ApiPlatform\State\ProcessorInterface;
use App\Application\MessageBus\CommandBusInterface;
use App\Domain\User\Service\CheckEmail;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

/**
 * Marks constructors in infrastructure service classes as "used" for dead-code detection.
 *
 * Classes implementing these interfaces are instantiated via dependency injection,
 * so their constructors appear unused to static analysis but are actually used at runtime.
 */
final class InfrastructureServiceUsageProvider extends ReflectionBasedMemberUsageProvider
{
    /**
     * @var list<class-string>
     */
    private const array DI_MANAGED_INTERFACES = [
        ProcessorInterface::class,
        CommandBusInterface::class,
        CheckEmail::class,
    ];

    #[\Override]
    public function shouldMarkMethodAsUsed(\ReflectionMethod $method): ?VirtualUsageData
    {
        // Only apply to constructors
        if ($method->getName() !== '__construct') {
            return null;
        }

        $declaringClass = $method->getDeclaringClass();

        // Skip interfaces and abstract classes
        if ($declaringClass->isInterface() || $declaringClass->isAbstract()) {
            return null;
        }

        // Check if the class implements any of the DI-managed interfaces
        foreach (self::DI_MANAGED_INTERFACES as $interface) {
            if ($declaringClass->implementsInterface($interface)) {
                return VirtualUsageData::withNote(
                    sprintf('Constructor used via DI (implements %s)', self::getShortClassName($interface)),
                );
            }
        }

        return null;
    }

    /**
     * @param class-string $className
     */
    private static function getShortClassName(string $className): string
    {
        $parts = explode('\\', $className);

        return end($parts);
    }
}
