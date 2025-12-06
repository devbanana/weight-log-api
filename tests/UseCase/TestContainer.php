<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\User\Command\LoginCommand;
use App\Application\User\Command\LoginHandler;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Command\RegisterUserHandler;
use App\Application\User\Query\FindUserAuthDataByEmailHandler;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;

/**
 * Test service container that provides spy objects for use case testing.
 * This allows testing the application core without real infrastructure.
 */
final class TestContainer
{
    public readonly InMemoryEventStore $eventStore;
    public readonly InMemoryCommandBus $commandBus;
    public readonly InMemoryQueryBus $queryBus;

    public function __construct()
    {
        $clock = new FrozenClock();
        $passwordHasher = new FakePasswordHasher();
        $userReadModel = new InMemoryUserReadModel();

        $this->eventStore = new InMemoryEventStore();
        $this->eventStore->addListener($userReadModel->handleEvent(...));

        $this->commandBus = new InMemoryCommandBus();
        $this->commandBus->register(
            RegisterUserCommand::class,
            new RegisterUserHandler(
                $this->eventStore,
                $userReadModel,
                $clock,
                $passwordHasher,
            ),
        );
        $this->commandBus->register(
            LoginCommand::class,
            new LoginHandler(
                $this->eventStore,
                $clock,
            ),
        );

        $this->queryBus = new InMemoryQueryBus();
        $this->queryBus->register(
            FindUserAuthDataByEmailQuery::class,
            new FindUserAuthDataByEmailHandler($userReadModel),
        );
    }
}
