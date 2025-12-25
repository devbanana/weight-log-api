<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB;

use App\Domain\User\Service\CheckEmail;
use App\Domain\User\ValueObject\Email;
use MongoDB\Collection;

/**
 * MongoDB implementation of the CheckEmail domain service.
 *
 * Queries the users projection to check email uniqueness.
 */
final readonly class MongoCheckEmail implements CheckEmail
{
    /**
     * @codeCoverageIgnore Empty constructor
     */
    public function __construct(
        private Collection $collection,
    ) {
    }

    #[\Override]
    public function isUnique(Email $email): bool
    {
        return $this->collection->countDocuments(['email' => $email->asString()]) === 0;
    }
}
