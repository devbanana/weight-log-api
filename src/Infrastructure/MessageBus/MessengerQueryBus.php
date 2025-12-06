<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageBus;

use App\Application\MessageBus\QueryBusInterface;
use App\Application\MessageBus\QueryInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Adapter that wraps Symfony Messenger as a QueryBusInterface.
 *
 * This is an infrastructure adapter that implements the application port
 * using Symfony Messenger for query dispatching.
 */
final readonly class MessengerQueryBus implements QueryBusInterface
{
    public function __construct(
        private MessageBusInterface $queryBus,
    ) {
    }

    #[\Override]
    public function dispatch(QueryInterface $query): mixed
    {
        try {
            $envelope = $this->queryBus->dispatch($query);
        } catch (HandlerFailedException $e) {
            // Unwrap the original exception from Messenger's wrapper
            $wrapped = $e->getWrappedExceptions();
            if (count($wrapped) === 1) {
                throw reset($wrapped);
            }

            throw $e;
        }

        // Extract the result from the HandledStamp
        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        return $handledStamp->getResult();
    }
}
