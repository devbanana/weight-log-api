<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\MessageBus\QueryBusInterface;
use App\Application\User\Query\GetUserInfoQuery;
use App\Domain\User\Exception\CouldNotFindUser;
use App\Infrastructure\Api\Resource\CurrentUserResource;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * State provider for the current user's info.
 *
 * Fetches the authenticated user's profile information.
 * This is a "driving adapter" in hexagonal architecture.
 *
 * @implements ProviderInterface<CurrentUserResource>
 */
final readonly class GetCurrentUserInfoProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
        private QueryBusInterface $queryBus,
    ) {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CurrentUserResource
    {
        $user = $this->security->getUser();
        assert($user !== null, 'User must be authenticated to access this endpoint');

        $userId = $user->getUserIdentifier();

        try {
            $userInfo = $this->queryBus->dispatch(
                new GetUserInfoQuery($userId),
            );
        } catch (CouldNotFindUser $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        }

        return new CurrentUserResource(
            id: $userInfo->id,
            email: $userInfo->email,
            displayName: $userInfo->displayName,
            registeredAt: $userInfo->registeredAt,
        );
    }
}
