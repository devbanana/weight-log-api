<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\QueryBusInterface;
use App\Application\User\Command\LoginCommand;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Infrastructure\Api\Resource\UserAuthenticationResource;
use App\Infrastructure\Api\Resource\UserAuthenticationResponse;
use App\Infrastructure\Security\SecurityUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * State processor for user authentication.
 *
 * Transforms the API request into queries/commands and generates JWT token.
 * This is a "driving adapter" in hexagonal architecture.
 *
 * @implements ProcessorInterface<UserAuthenticationResource, UserAuthenticationResponse>
 */
final readonly class AuthenticateUserProcessor implements ProcessorInterface
{
    public function __construct(
        private QueryBusInterface $queryBus,
        private CommandBusInterface $commandBus,
        private JWTTokenManagerInterface $jwtManager,
    ) {
    }

    /**
     * @param UserAuthenticationResource $data
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): UserAuthenticationResponse
    {
        // 1. Query for auth data by email
        $authData = $this->queryBus->dispatch(
            new FindUserAuthDataByEmailQuery($data->email),
        );

        if ($authData === null) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        // 2. Dispatch login command (verifies password, records event)
        try {
            $this->commandBus->dispatch(new LoginCommand(
                userId: $authData->userId,
                password: $data->password,
            ));
        } catch (CouldNotAuthenticate) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid credentials.');
        }

        // 3. Generate JWT token using Lexik
        $token = $this->jwtManager->create(
            new SecurityUser($authData->userId, $authData->roles),
        );

        return new UserAuthenticationResponse($token);
    }
}
