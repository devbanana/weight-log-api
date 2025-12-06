<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\CommandInterface;

/**
 * In-memory command bus for testing.
 *
 * Maps command classes to their handlers.
 *
 * @internal Only to be used in tests
 */
final class InMemoryCommandBus implements CommandBusInterface
{
    /**
     * @var array<class-string<CommandInterface>, object>
     */
    private array $handlers = [];

    /**
     * Register a handler for a command class.
     *
     * The handler must be an invokable object that accepts the specific command type.
     *
     * @param class-string<CommandInterface> $commandClass
     * @param object                         $handler      An invokable handler (has __invoke method)
     */
    public function register(string $commandClass, object $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    #[\Override]
    public function dispatch(CommandInterface $command): void
    {
        $commandClass = $command::class;

        if (!isset($this->handlers[$commandClass])) {
            throw new \RuntimeException("No handler registered for command: {$commandClass}");
        }

        $handler = $this->handlers[$commandClass];
        assert(is_callable($handler));
        $handler($command);
    }
}
