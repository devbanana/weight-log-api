<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\User\Command\LoginCommand;
use App\Application\User\Command\LoginHandler;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Command\RegisterUserHandler;
use App\Application\User\Query\FindUserAuthDataByEmailHandler;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;
use Symfony\Component\Clock\MockClock;

/**
 * Test service container that provides spy objects for use case testing.
 * This allows testing the application core without real infrastructure.
 */
final class TestContainer
{
    public readonly InMemoryEventStore $eventStore;
    public readonly InMemoryCommandBus $commandBus;
    public readonly InMemoryQueryBus $queryBus;
    public readonly MockClock $clock;

    public function __construct()
    {
        $this->clock = new MockClock('2025-12-12 12:00:00 UTC');
        $passwordHasher = new FakePasswordHasher();
        $checkEmail = new InMemoryCheckEmail();
        $findUserAuthData = new InMemoryFindUserAuthData();

        $this->eventStore = new InMemoryEventStore();
        $this->eventStore->addListener($checkEmail->handleEvent(...));
        $this->eventStore->addListener($findUserAuthData->handleEvent(...));

        $this->commandBus = new InMemoryCommandBus();
        $this->commandBus->register(
            RegisterUserCommand::class,
            new RegisterUserHandler(
                $this->eventStore,
                $checkEmail,
                $this->clock,
                $passwordHasher,
            ),
        );
        $this->commandBus->register(
            LoginCommand::class,
            new LoginHandler(
                $this->eventStore,
                $this->clock,
            ),
        );

        $this->queryBus = new InMemoryQueryBus();
        $this->queryBus->register(
            FindUserAuthDataByEmailQuery::class,
            new FindUserAuthDataByEmailHandler($findUserAuthData),
        );
    }
}
