<?php

declare(strict_types=1);

namespace App\Application\MessageBus;

/**
 * Interface for command handlers.
 *
 * Command handlers process commands and execute the corresponding use case logic.
 *
 * @template TCommand of CommandInterface
 */
interface CommandHandlerInterface
{
    /**
     * Handle the command.
     *
     * @param TCommand $command
     */
    public function __invoke(CommandInterface $command): void;
}
