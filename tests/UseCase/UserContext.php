<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\User\Command\RegisterUserCommand;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\User;
use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Component\Uid\Uuid;
use Webmozart\Assert\Assert;

/**
 * Use case context tests the application core using TestServiceContainer with spy objects.
 * This tests the business logic without touching real infrastructure (database, HTTP, etc).
 *
 * @internal
 */
final class UserContext implements Context
{
    private TestContainer $container;
    private ?string $registeredUserId = null;
    private ?\Throwable $caughtException = null;

    public function __construct()
    {
        $this->container = new TestContainer();
    }

    #[When('I register with:')]
    public function iRegisterWith(TableNode $table): void
    {
        $data = $table->getRowsHash();
        Assert::keyExists($data, 'email');
        Assert::string($data['email']);

        // Generate client-side UUID (v7 is time-ordered, better for databases)
        $this->registeredUserId = Uuid::v7()->toString();

        $command = new RegisterUserCommand(
            userId: $this->registeredUserId,
            email: $data['email'],
        );

        try {
            $this->container->getCommandBus()->dispatch($command);
        } catch (\Throwable $e) {
            $this->caughtException = $e;
        }
    }

    #[Then('I should be registered')]
    public function iShouldBeRegistered(): void
    {
        Assert::null($this->caughtException, 'Registration should not have thrown an exception');
        Assert::string($this->registeredUserId, 'No user ID was registered');

        // Verify user exists by checking events were stored and can be reconstituted
        $events = $this->container->getEventStore()->getEvents($this->registeredUserId, User::class);
        Assert::notEmpty($events, 'No events were stored for the user');

        // Verify we can reconstitute the user from events (would throw if invalid)
        User::reconstitute($events);
    }

    #[Given('a user exists with email :email')]
    public function aUserExistsWithEmail(string $email): void
    {
        $command = new RegisterUserCommand(
            userId: Uuid::v7()->toString(),
            email: $email,
        );

        $this->container->getCommandBus()->dispatch($command);
    }

    #[Then('registration should fail')]
    public function registrationShouldFail(): void
    {
        Assert::isInstanceOf(
            $this->caughtException,
            UserAlreadyExistsException::class,
            'Expected UserAlreadyExistsException to be thrown',
        );
    }
}
