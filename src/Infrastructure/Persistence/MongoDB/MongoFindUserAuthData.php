<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB;

use App\Application\User\Query\FindUserAuthData;
use App\Application\User\Query\UserAuthData;
use App\Domain\User\ValueObject\Email;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

/**
 * MongoDB implementation of the FindUserAuthData finder.
 */
final readonly class MongoFindUserAuthData implements FindUserAuthData
{
    /**
     * @codeCoverageIgnore Empty constructor
     */
    public function __construct(
        private Collection $collection,
    ) {
    }

    #[\Override]
    public function byEmail(Email $email): ?UserAuthData
    {
        $document = $this->collection->findOne(
            ['email' => $email->asString()],
            ['projection' => ['_id' => 1]],
        );

        if ($document === null) {
            return null;
        }

        assert($document instanceof BSONDocument);

        $userId = $document['_id'];
        assert(is_string($userId) && $userId !== '');

        return new UserAuthData($userId);
    }
}
