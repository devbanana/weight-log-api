<?php

declare(strict_types=1);

use Behat\Behat\Context\Context;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * API Context - Tests full HTTP stack end-to-end
 *
 * This context tests the complete application through HTTP requests.
 * It verifies that API Platform resources, state processors/providers,
 * command/query handlers, and domain model all work together.
 *
 * Uses real database (test environment), real HTTP stack.
 * Slowest but most comprehensive tests.
 */
final class ApiContext implements Context
{
    private ?Response $response = null;
    private ?string $authToken = null;

    public function __construct(
        private readonly KernelInterface $kernel
    ) {
    }

    /**
     * @Given no user exists with email :email
     */
    public function noUserExistsWithEmail(string $email): void
    {
        // Clean up database in test environment
        // Will be implemented when we create API endpoints
    }

    /**
     * @Given a user exists with email :email
     */
    public function aUserExistsWithEmail(string $email): void
    {
        // Create a user in the test database
        // Will be implemented when we create API endpoints
    }

    /**
     * @When I register with email :email and password :password
     */
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        // Make HTTP POST request to /auth/register
        $request = Request::create(
            uri: '/auth/register',
            method: 'POST',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'email' => $email,
                'password' => $password,
            ])
        );

        $this->response = $this->kernel->handle($request);
    }

    /**
     * @Then the user should be registered
     */
    public function theUserShouldBeRegistered(): void
    {
        // Assert HTTP response is 201 Created
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }

        if ($this->response->getStatusCode() !== 201) {
            throw new \RuntimeException(
                sprintf(
                    'Expected 201 Created, got %d. Response: %s',
                    $this->response->getStatusCode(),
                    $this->response->getContent()
                )
            );
        }
    }

    /**
     * @Then registration should fail
     */
    public function registrationShouldFail(): void
    {
        // Assert HTTP response is 409 Conflict or 422 Unprocessable
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }

        if (!in_array($this->response->getStatusCode(), [409, 422], true)) {
            throw new \RuntimeException(
                sprintf(
                    'Expected 409 or 422 response, got %d. Response: %s',
                    $this->response->getStatusCode(),
                    $this->response->getContent()
                )
            );
        }
    }

    /**
     * @Then I should receive a :statusCode response
     */
    public function iShouldReceiveAResponse(int $statusCode): void
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }

        if ($this->response->getStatusCode() !== $statusCode) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %d response, got %d. Response: %s',
                    $statusCode,
                    $this->response->getStatusCode(),
                    $this->response->getContent()
                )
            );
        }
    }
}
