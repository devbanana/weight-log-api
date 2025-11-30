<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageBus;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\CommandInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Adapter that wraps Symfony Messenger as a CommandBusInterface.
 *
 * This is an infrastructure adapter that implements the application port
 * using Symfony Messenger for command dispatching.
 */
final readonly class MessengerCommandBus implements CommandBusInterface
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {
    }

    #[\Override]
    public function dispatch(CommandInterface $command): void
    {
        $this->commandBus->dispatch($command);
    }
}
