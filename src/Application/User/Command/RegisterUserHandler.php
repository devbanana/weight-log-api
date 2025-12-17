<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Security\PasswordHasherInterface;
use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\User\Exception\CouldNotRegister;
use App\Domain\User\User;
use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\DateOfBirth;
use App\Domain\User\ValueObject\DisplayName;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\PlainPassword;
use App\Domain\User\ValueObject\UserId;
use Psr\Clock\ClockInterface;

/**
 * Handler for RegisterUserCommand.
 *
 * Orchestrates the user registration use case.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private EventStoreInterface $eventStore,
        private UserReadModelInterface $userReadModel,
        private ClockInterface $clock,
        private PasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(RegisterUserCommand $command): void
    {
        $email = Email::fromString($command->email);

        if ($this->userReadModel->existsWithEmail($email)) {
            throw CouldNotRegister::becauseEmailIsAlreadyInUse($email);
        }

        $userId = UserId::fromString($command->userId);
        $dateOfBirth = DateOfBirth::fromString($command->dateOfBirth);
        $displayName = DisplayName::fromString($command->displayName);
        $plainPassword = PlainPassword::fromString($command->password);
        $hashedPassword = $this->passwordHasher->hash($plainPassword);

        $user = User::register(
            $userId,
            $email,
            $dateOfBirth,
            $displayName,
            $hashedPassword,
            $this->clock->now(),
        );

        $events = $user->releaseEvents();
        assert(count($events) > 0);

        $this->eventStore->append(
            $userId->asString(),
            User::class,
            $events,
            expectedVersion: 0,
        );
    }
}
