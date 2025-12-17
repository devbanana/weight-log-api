<?php

declare(strict_types=1);

namespace App\Infrastructure\Api\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Application\MessageBus\CommandBusInterface;
use App\Application\User\Command\RegisterUserCommand;
use App\Domain\User\Exception\CouldNotRegister;
use App\Domain\User\Exception\RegistrationFailureReason;
use App\Infrastructure\Api\Resource\UserRegistrationResource;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * State processor for user registration.
 *
 * Transforms the API request into a RegisterUserCommand and dispatches it to the command bus.
 * This is a "driving adapter" in hexagonal architecture - it drives the application core.
 *
 * @implements ProcessorInterface<UserRegistrationResource, void>
 */
final readonly class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @param UserRegistrationResource $data
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $command = new RegisterUserCommand(
            userId: Uuid::v7()->toRfc4122(),
            email: $data->email,
            dateOfBirth: $data->dateOfBirth,
            displayName: $data->displayName,
            password: $data->password,
        );

        try {
            $this->commandBus->dispatch($command);
        } catch (CouldNotRegister $e) {
            throw match ($e->reason) {
                RegistrationFailureReason::EmailAlreadyInUse => new ConflictHttpException($e->getMessage(), $e),
                RegistrationFailureReason::UserTooYoung,
                RegistrationFailureReason::DateOfBirthInTheFuture => new UnprocessableEntityHttpException($e->getMessage(), $e),
            };
        }
    }
}
