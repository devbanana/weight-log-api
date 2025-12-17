<?php

declare(strict_types=1);

namespace App\Tests\UseCase;

use App\Application\User\Command\LoginCommand;
use App\Application\User\Command\RegisterUserCommand;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;
use App\Domain\User\Event\UserLoggedIn;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Domain\User\Exception\CouldNotRegister;
use App\Domain\User\Exception\RegistrationFailureReason;
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
 * This tests the business logic without touching real infrastructure (database, HTTP, etc.).
 *
 * @internal
 */
final class UserContext implements Context
{
    private TestContainer $container;
    private ?string $registeredUserId = null;
    private ?string $loggedInUserId = null;
    private ?\Throwable $caughtException = null;

    public function __construct()
    {
        $this->container = new TestContainer();
    }

    #[Given('today\'s date is :date')]
    public function todaysDateIs(string $date): void
    {
        $dateTime = \DateTimeImmutable::createFromFormat('F j, Y H:i:s', $date . ' 12:00:00', new \DateTimeZone('UTC'));
        assert($dateTime instanceof \DateTimeImmutable);
        $this->container->clock->modify($dateTime->format('Y-m-d H:i:s'));
    }

    #[When('I register with:')]
    public function iRegisterWith(TableNode $table): void
    {
        $data = $table->getRowsHash();
        $email = $data['email'];
        $dateOfBirth = $data['dateOfBirth'];
        $displayName = $data['displayName'];
        $password = $data['password'];
        assert(is_string($email) && is_string($dateOfBirth) && is_string($displayName) && is_string($password));

        $this->registerWithData($email, $dateOfBirth, $displayName, $password);
    }

    #[When('I register with a whitespace-only display name')]
    public function iRegisterWithAWhitespaceOnlyDisplayName(): void
    {
        $this->registerWithData('bob@example.com', '1990-05-15', '   ', 'SecurePass123!');
    }

    #[Then('I should be registered')]
    public function iShouldBeRegistered(): void
    {
        Assert::null($this->caughtException, 'Registration should not have thrown an exception');
        Assert::string($this->registeredUserId, 'No user ID was registered');

        // Verify user exists by checking events were stored and can be reconstituted
        $events = $this->container->eventStore->getEvents($this->registeredUserId, User::class);
        Assert::notEmpty($events, 'No events were stored for the user');

        // Verify we can reconstitute the user from events (would throw if invalid)
        User::reconstitute($events);
    }

    #[Given('a user exists with email :email')]
    public function aUserExistsWithEmail(string $email): void
    {
        $this->aUserExistsWithEmailAndPassword($email, 'DefaultPass123!');
    }

    #[Given('a user exists with email :email and password :password')]
    public function aUserExistsWithEmailAndPassword(string $email, string $password): void
    {
        $command = new RegisterUserCommand(
            userId: Uuid::v7()->toString(),
            email: $email,
            dateOfBirth: '1990-01-01',
            displayName: 'Existing User',
            password: $password,
        );

        $this->container->commandBus->dispatch($command);
    }

    #[Then('registration should fail due to duplicate email')]
    public function registrationShouldFailDueToDuplicateEmail(): void
    {
        Assert::isInstanceOf(
            $this->caughtException,
            CouldNotRegister::class,
            'Expected CouldNotRegister exception to be thrown',
        );
    }

    #[Then('registration should fail due to invalid email format')]
    #[Then('registration should fail due to invalid password')]
    #[Then('registration should fail due to invalid date of birth')]
    #[Then('registration should fail due to invalid display name')]
    public function registrationShouldFailDueToValidationError(): void
    {
        Assert::isInstanceOf(
            $this->caughtException,
            \InvalidArgumentException::class,
            'Expected InvalidArgumentException to be thrown',
        );
    }

    #[Then('registration should fail because user is under 18')]
    public function registrationShouldFailBecauseUserIsUnder18(): void
    {
        Assert::isInstanceOf(
            $this->caughtException,
            CouldNotRegister::class,
            'Expected CouldNotRegister exception to be thrown',
        );
        Assert::contains(
            $this->caughtException->getMessage(),
            'at least',
            'Expected exception message to mention age requirement',
        );
    }

    #[Then('registration should fail because date of birth is in the future')]
    public function registrationShouldFailBecauseDateOfBirthIsInTheFuture(): void
    {
        Assert::isInstanceOf(
            $this->caughtException,
            CouldNotRegister::class,
            'Expected CouldNotRegister exception to be thrown',
        );
        Assert::same(
            RegistrationFailureReason::DateOfBirthInTheFuture,
            $this->caughtException->reason,
            'Expected registration to fail because date of birth is in the future',
        );
    }

    #[When('I log in with email :email and password :password')]
    public function iLogInWithEmailAndPassword(string $email, string $password): void
    {
        try {
            $authData = $this->container->queryBus->dispatch(
                new FindUserAuthDataByEmailQuery($email),
            );

            if ($authData === null) {
                // User not found - let Then step handle this via loggedInUserId check
                return;
            }

            $this->loggedInUserId = $authData->userId;

            $this->container->commandBus->dispatch(new LoginCommand(
                userId: $authData->userId,
                password: $password,
            ));
        } catch (\Throwable $e) {
            $this->caughtException = $e;
        }
    }

    #[Then('I should be logged in')]
    public function iShouldBeLoggedIn(): void
    {
        Assert::null($this->caughtException, 'Login should not have thrown an exception');
        Assert::notNull($this->loggedInUserId, 'No user was logged in');

        // Verify UserLoggedIn event was recorded
        $events = $this->container->eventStore->getEvents($this->loggedInUserId, User::class);
        $loginEvents = array_filter($events, static fn ($e) => $e instanceof UserLoggedIn);
        Assert::notEmpty($loginEvents, 'UserLoggedIn event should have been recorded');
    }

    #[Then('login should fail due to invalid credentials')]
    public function loginShouldFailDueToInvalidCredentials(): void
    {
        // Login fails if: user not found (loggedInUserId is null) OR wrong password (exception)
        Assert::true(
            $this->loggedInUserId === null || $this->caughtException instanceof CouldNotAuthenticate,
            'Expected login to fail due to invalid credentials',
        );
    }

    #[Then('login should fail due to invalid email format')]
    #[Then('login should fail due to invalid password')]
    public function loginShouldFailDueToValidationError(): void
    {
        Assert::isInstanceOf(
            $this->caughtException,
            \InvalidArgumentException::class,
            'Expected InvalidArgumentException to be thrown',
        );
    }

    private function registerWithData(string $email, string $dateOfBirth, string $displayName, string $password): void
    {
        // Generate client-side UUID (v7 is time-ordered, better for databases)
        $this->registeredUserId = Uuid::v7()->toString();

        $command = new RegisterUserCommand(
            userId: $this->registeredUserId,
            email: $email,
            dateOfBirth: $dateOfBirth,
            displayName: $displayName,
            password: $password,
        );

        try {
            $this->container->commandBus->dispatch($command);
        } catch (\Throwable $e) {
            $this->caughtException = $e;
        }
    }
}
