<?php

declare(strict_types=1);

namespace App\Application\MessageBus;

/**
 * Query bus interface for dispatching queries.
 */
interface QueryBusInterface
{
    /**
     * Dispatch a query and return the result.
     *
     * @template TResult
     *
     * @param QueryInterface<TResult> $query
     *
     * @return TResult
     */
    public function dispatch(QueryInterface $query): mixed;
}
