<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\MessageBus\QueryBusInterface;
use App\Application\MessageBus\QueryInterface;

/**
 * In-memory query bus for testing.
 *
 * Maps query classes to their handlers.
 *
 * @internal Only to be used in tests
 */
final class InMemoryQueryBus implements QueryBusInterface
{
    /**
     * @var array<string, object>
     */
    private array $handlers = [];

    /**
     * Register a handler for a query class.
     *
     * The handler must be an invokable object that accepts the specific query type.
     *
     * @param class-string<QueryInterface<mixed>> $queryClass
     * @param object                              $handler    An invokable handler (has __invoke method)
     */
    public function register(string $queryClass, object $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    #[\Override]
    public function dispatch(QueryInterface $query): mixed
    {
        $queryClass = $query::class;

        if (!isset($this->handlers[$queryClass])) {
            throw new \RuntimeException("No handler registered for query: {$queryClass}");
        }

        $handler = $this->handlers[$queryClass];
        assert(is_callable($handler));

        return $handler($query);
    }
}
