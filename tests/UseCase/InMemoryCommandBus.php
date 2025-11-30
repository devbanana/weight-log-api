<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\CommandHandlerInterface;
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
     * @var array<class-string<CommandInterface>, CommandHandlerInterface<CommandInterface>>
     */
    private array $handlers = [];

    /**
     * @template TCommand of CommandInterface
     *
     * @param class-string<TCommand>            $commandClass
     * @param CommandHandlerInterface<TCommand> $handler
     */
    public function register(string $commandClass, CommandHandlerInterface $handler): void
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

        ($this->handlers[$commandClass])($command);
    }
}
