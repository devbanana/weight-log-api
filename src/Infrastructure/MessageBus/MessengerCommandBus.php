<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageBus;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\CommandInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
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
        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $e) {
            // Unwrap the original exception from Messenger's wrapper
            // This allows domain exceptions to propagate cleanly to callers
            $wrapped = $e->getWrappedExceptions();
            if (count($wrapped) === 1) {
                throw reset($wrapped);
            }

            throw $e;
        }
    }
}
