<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Command\RegisterUserHandler;
use App\Domain\Common\EventStore\EventStoreInterface;

/**
 * Test service container that provides spy objects for use case testing.
 * This allows testing the application core without real infrastructure.
 */
final class TestContainer
{
    private InMemoryEventStore $eventStore;
    private InMemoryUserReadModel $userReadModel;
    private InMemoryCommandBus $commandBus;
    private FrozenClock $clock;
    private FakePasswordHasher $passwordHasher;

    public function __construct()
    {
        // Create clock (frozen for deterministic tests)
        $this->clock = new FrozenClock();

        // Create password hasher (uses real bcrypt with low cost)
        $this->passwordHasher = new FakePasswordHasher();

        // Create read model (projection)
        $this->userReadModel = new InMemoryUserReadModel();

        // Create event store and wire up projection updates
        $this->eventStore = new InMemoryEventStore();
        $this->eventStore->addListener($this->userReadModel->handleEvent(...));

        // Command bus with real handlers
        $this->commandBus = new InMemoryCommandBus();
        $this->commandBus->register(
            RegisterUserCommand::class,
            new RegisterUserHandler(
                $this->eventStore,
                $this->userReadModel,
                $this->clock,
                $this->passwordHasher,
            ),
        );
    }

    public function getCommandBus(): CommandBusInterface
    {
        return $this->commandBus;
    }

    public function getEventStore(): EventStoreInterface
    {
        return $this->eventStore;
    }
}
