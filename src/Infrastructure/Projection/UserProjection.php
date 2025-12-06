<?php

declare(strict_types=1);

namespace App\Infrastructure\Projection;

use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Event\UserRegistered;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Projects user domain events to the MongoDB users read model.
 */
final readonly class UserProjection
{
    public function __construct(
        private Collection $collection,
    ) {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onUserRegistered(UserRegistered $event): void
    {
        $this->collection->updateOne(
            ['_id' => $event->id],
            ['$set' => [
                'email' => $event->email,
                'hashed_password' => $event->hashedPassword,
                'registered_at' => new UTCDateTime($event->occurredAt),
            ]],
            // Idempotent: replaying events won't cause duplicates
            ['upsert' => true],
        );
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onUserLoggedIn(UserLoggedIn $event): void
    {
        $this->collection->updateOne(
            ['_id' => $event->id],
            ['$set' => [
                'last_login_at' => new UTCDateTime($event->occurredAt),
            ]],
        );
    }
}
