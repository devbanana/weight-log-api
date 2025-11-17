<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;

/**
 * Domain Context - Tests pure business logic
 *
 * This context tests the domain model directly without any infrastructure.
 * It's the fastest suite and focuses on business rules and domain behavior.
 *
 * NO framework dependencies, NO database, NO HTTP.
 * Just pure domain logic.
 */
final class DomainContext implements Context
{
    /**
     * @Given no user exists with email :email
     */
    public function noUserExistsWithEmail(string $email): void
    {
        // In-memory repository - no database needed for domain tests
        // Will be implemented when we create the domain model
    }

    /**
     * @Given a user exists with email :email
     */
    public function aUserExistsWithEmail(string $email): void
    {
        // Create a user in the in-memory repository
        // Will be implemented when we create the domain model
    }

    /**
     * @When I register with email :email and password :password
     */
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        // Call domain model directly:
        // $this->user = User::register(Email::fromString($email), ...)
        // Will be implemented when we create the domain model
    }

    /**
     * @Then the user should be registered
     */
    public function theUserShouldBeRegistered(): void
    {
        // Assert domain state
        // Will be implemented when we create the domain model
    }

    /**
     * @Then registration should fail
     */
    public function registrationShouldFail(): void
    {
        // Assert that UserAlreadyExistsException was thrown
        // Will be implemented when we create the domain model
    }
}
