<?php

declare(strict_types=1);

namespace UseCase;

use Behat\Behat\Context\Context;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;

/**
 * Use case context tests the application core using TestServiceContainer with spy objects.
 * This tests the business logic without touching real infrastructure (database, HTTP, etc).
 */
final class UserContext implements Context
{
    private ?object $registeredUser = null;
    private ?\Throwable $thrownException = null;

    /**
     * @When I register with email :email and password :password
     */
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        try {
            // TODO: Dispatch RegisterUserCommand through TestServiceContainer
            // Example:
            // $command = new RegisterUserCommand($email, $password, ...);
            // $this->messageBus->dispatch($command);
            // $this->registeredUser = $this->userRepository->findByEmail($email);

            throw new \RuntimeException('Not yet implemented');
        } catch (\Throwable $e) {
            $this->thrownException = $e;
        }
    }

    /**
     * @Then the user should be registered
     */
    public function theUserShouldBeRegistered(): void
    {
        if ($this->thrownException !== null) {
            throw new \RuntimeException(
                'Expected user to be registered, but exception was thrown: ' .
                $this->thrownException->getMessage()
            );
        }

        if ($this->registeredUser === null) {
            throw new \RuntimeException('Expected user to be registered, but no user was created');
        }

        // TODO: Verify user was registered correctly
        // Example:
        // assert($this->registeredUser->getEmail() === $email);
    }

    /**
     * @Given a user exists with email :email
     */
    public function aUserExistsWithEmail(string $email): void
    {
        // TODO: Create a user directly in the test repository (spy object)
        // Example:
        // $user = User::register(Email::fromString($email), ...);
        // $this->userRepository->add($user);

        throw new \RuntimeException('Not yet implemented');
    }

    /**
     * @Then registration should fail
     */
    public function registrationShouldFail(): void
    {
        if ($this->thrownException === null) {
            throw new \RuntimeException('Expected registration to fail, but it succeeded');
        }

        // TODO: Verify the correct exception was thrown
        // Example:
        // if (!$this->thrownException instanceof EmailAlreadyInUseException) {
        //     throw new \RuntimeException('Wrong exception type thrown');
        // }
    }
}
