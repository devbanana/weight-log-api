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
    /**
     * @codeCoverageIgnore Empty constructor
     */
    public function __construct(
        private Collection $collection,
    ) {
    }

    /**
     * @return non-empty-string|null
     */
    #[\Override]
    public function findUserIdByEmail(Email $email): ?string
    {
        $document = $this->collection->findOne(
            ['email' => $email->asString()],
            [
                // Only fetch the _id field
                'projection' => ['_id' => 1],
                // Return as PHP array instead of BSONDocument
                'typeMap' => ['root' => 'array'],
            ],
        );

        if ($document === null) {
            return null;
        }

        assert(is_array($document));
        assert(isset($document['_id']));

        $userId = $document['_id'];
        assert(is_string($userId) && $userId !== '');

        return $userId;
    }
}
