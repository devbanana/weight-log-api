<?php

declare(strict_types=1);

namespace App\Application\MessageBus;

/**
 * Command bus interface for dispatching commands.
 */
interface CommandBusInterface
{
    /**
     * Dispatch a command.
     *
     * @template TCommand of CommandInterface
     *
     * @param TCommand $command
     */
    public function dispatch(CommandInterface $command): void;
}
