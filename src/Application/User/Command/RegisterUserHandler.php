<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Security\PasswordHasherInterface;
use App\Domain\Common\EventStore\EventStoreInterface;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\User;
use App\Domain\User\UserReadModelInterface;
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
            throw UserAlreadyExistsException::withEmail($email);
        }

        $userId = UserId::fromString($command->userId);
        $plainPassword = PlainPassword::fromString($command->password);
        $hashedPassword = $this->passwordHasher->hash($plainPassword);

        $user = User::register(
            $userId,
            $email,
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
