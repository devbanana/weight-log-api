<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB;

use App\Application\User\Query\GetUserInfo;
use App\Application\User\Query\UserInfo;
use App\Domain\User\Exception\CouldNotFindUser;
use App\Domain\User\ValueObject\UserId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Model\BSONDocument;

/**
 * MongoDB implementation of the GetUserInfo finder.
 */
final readonly class MongoGetUserInfo implements GetUserInfo
{
    /**
     * @codeCoverageIgnore Empty constructor
     */
    public function __construct(
        private Collection $collection,
    ) {
    }

    #[\Override]
    public function byId(UserId $userId): UserInfo
    {
        $document = $this->collection->findOne(
            ['_id' => $userId->asString()],
            ['projection' => [
                '_id' => 1,
                'email' => 1,
                'display_name' => 1,
                'registered_at' => 1,
            ]],
        );

        if ($document === null) {
            throw CouldNotFindUser::withId($userId);
        }

        assert($document instanceof BSONDocument);

        $id = $document['_id'];
        $email = $document['email'];
        $displayName = $document['display_name'];
        $registeredAt = $document['registered_at'];

        assert(is_string($id) && $id !== '');
        assert(is_string($email) && $email !== '');
        assert(is_string($displayName) && $displayName !== '');
        assert($registeredAt instanceof UTCDateTime);

        return new UserInfo(
            id: $id,
            email: $email,
            displayName: $displayName,
            registeredAt: $registeredAt->toDateTime()->format(\DateTimeInterface::ATOM),
        );
    }
}
