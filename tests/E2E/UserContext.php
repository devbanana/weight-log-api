<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * End-to-end context tests the full application stack with real HTTP requests.
 * This tests the complete system including API Platform, Doctrine, JWT, etc.
 *
 * @internal
 */
final class UserContext implements Context
{
    private ?Response $response = null;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {
    }

    #[Given('no user exists with email :email')]
    public function noUserExistsWithEmail(string $email): void
    {
        // TODO: Clean up database if needed
        // For now, we rely on test database being reset between tests
    }

    #[Given('a user exists with email :email')]
    public function aUserExistsWithEmail(string $email): void
    {
        // Register a user first
        $this->iRegisterWithEmailAndPassword($email, 'SomePassword123!');

        // Verify it was created
        if ($this->response?->getStatusCode() !== 201) {
            throw new \RuntimeException('Failed to create user for test setup');
        }

        // Reset response for the actual test
        $this->response = null;
    }

    #[When('I register with:')]
    public function iRegisterWith(TableNode $table): void
    {
        throw new PendingException();
    }

    #[When('I register with email :email and password :password')]
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        $request = Request::create(
            uri: '/api/auth/register',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'email' => $email,
                'password' => $password,
                // TODO: Add other required fields (display_name, date_of_birth, etc.)
            ], JSON_THROW_ON_ERROR)
        );

        $this->response = $this->kernel->handle($request);
    }

    #[Then('I should be registered')]
    public function iShouldBeRegistered(): void
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }

        $content = $this->response->getContent();
        if ($content === false) {
            throw new \RuntimeException('There was an error retrieving the response content');
        }

        $statusCode = $this->response->getStatusCode();
        if ($statusCode !== 201) {
            throw new \RuntimeException(
                sprintf(
                    'Expected 201 status code, got %d. Response: %s',
                    $statusCode,
                    $content
                )
            );
        }
    }

    #[Then('registration should fail')]
    public function registrationShouldFail(): void
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }

        $statusCode = $this->response->getStatusCode();
        if ($statusCode < 400) {
            throw new \RuntimeException(
                sprintf(
                    'Expected error status code (4xx or 5xx), got %d',
                    $statusCode
                )
            );
        }
    }

    #[Then('I should receive a :statusCode response')]
    public function iShouldReceiveAResponse(int $statusCode): void
    {
        if ($this->response === null) {
            throw new \RuntimeException('No response received');
        }

        $content = $this->response->getContent();
        if ($content === false) {
            throw new \RuntimeException('There was an error retrieving the response content');
        }

        $actualStatusCode = $this->response->getStatusCode();
        if ($actualStatusCode !== $statusCode) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %d status code, got %d. Response: %s',
                    $statusCode,
                    $actualStatusCode,
                    $content
                )
            );
        }
    }
}
