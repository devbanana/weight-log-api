<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB;

use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\Email;
use MongoDB\Collection;

/**
 * MongoDB implementation of the user read model.
 */
final readonly class MongoUserReadModel implements UserReadModelInterface
{
    public function __construct(
        private Collection $collection,
    ) {
    }

    #[\Override]
    public function existsWithEmail(Email $email): bool
    {
        return $this->collection->countDocuments(['email' => $email->asString()]) > 0;
    }
}
