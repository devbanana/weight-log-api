<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Application Context - Tests use cases (CQRS)
 *
 * This context tests command and query handlers through Symfony Messenger.
 * It verifies that the application layer correctly orchestrates domain logic.
 *
 * Uses in-memory repositories, but with real command/query bus.
 */
final class ApplicationContext implements Context
{
    public function __construct(
        private readonly KernelInterface $kernel
    ) {
    }

    /**
     * @Given no user exists with email :email
     */
    public function noUserExistsWithEmail(string $email): void
    {
        // Clear in-memory repository
        // Will be implemented when we create application layer
    }

    /**
     * @Given a user exists with email :email
     */
    public function aUserExistsWithEmail(string $email): void
    {
        // Create a user via RegisterUser command
        // Will be implemented when we create application layer
    }

    /**
     * @When I register with email :email and password :password
     */
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        // Dispatch command through message bus:
        // $command = new RegisterUser($email, $password);
        // $this->messageBus->dispatch($command);
        // Will be implemented when we create command handlers
    }

    /**
     * @Then the user should be registered
     */
    public function theUserShouldBeRegistered(): void
    {
        // Query the repository to verify
        // Will be implemented when we create query handlers
    }

    /**
     * @Then registration should fail
     */
    public function registrationShouldFail(): void
    {
        // Assert that command handler threw exception or returned failure
        // Will be implemented when we create command handlers
    }
}
