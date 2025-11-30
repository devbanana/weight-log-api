<?php

declare(strict_types=1);

namespace App\Application\MessageBus;

/**
 * Marker interface for commands.
 *
 * Commands represent write operations (state changes) in the system.
 * They are dispatched through the command bus to their respective handlers.
 */
interface CommandInterface
{
}
