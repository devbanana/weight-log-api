<?php

declare(strict_types=1);

namespace App\Application\MessageBus;

/**
 * Marker interface for queries.
 *
 * Queries represent read operations in the system.
 * They are dispatched through the query bus to their respective handlers
 * and return data without modifying state.
 *
 * @template-covariant TResult
 */
interface QueryInterface
{
}
