<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use MongoDB\Client;
use MongoDB\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Webmozart\Assert\Assert;

/**
 * End-to-end context tests the full application stack with real HTTP requests.
 * This tests the complete system including API Platform, MongoDB, etc.
 *
 * @internal
 */
final class UserContext implements Context
{
    private ?Response $response = null;
    private Database $database;

    public function __construct(
        private readonly KernelInterface $kernel,
        Client $mongoClient,
        string $mongoDatabase,
    ) {
        $this->database = $mongoClient->selectDatabase($mongoDatabase);
    }

    #[BeforeScenario]
    public function cleanDatabase(BeforeScenarioScope $scope): void
    {
        // Drop and recreate collections to ensure clean state
        $this->database->dropCollection('events');
        $this->database->dropCollection('users');
    }

    #[Given('a user exists with email :email')]
    public function aUserExistsWithEmail(string $email): void
    {
        $this->iRegisterWithEmail($email);

        Assert::same(
            $this->response?->getStatusCode(),
            201,
            sprintf('Failed to create user for test setup. Response: %s', $this->getResponseContent())
        );

        $this->response = null;
    }

    #[When('I register with email :email')]
    public function iRegisterWithEmail(string $email): void
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
            ], JSON_THROW_ON_ERROR)
        );

        $this->response = $this->kernel->handle($request);
    }

    #[Then('I should be registered')]
    public function iShouldBeRegistered(): void
    {
        Assert::notNull($this->response, 'No response received');
        Assert::same(
            $this->response->getStatusCode(),
            201,
            sprintf('Expected 201 status code. Response: %s', $this->getResponseContent())
        );
    }

    #[Then('registration should fail due to duplicate email')]
    public function registrationShouldFailDueToDuplicateEmail(): void
    {
        Assert::notNull($this->response, 'No response received');
        Assert::same(
            $this->response->getStatusCode(),
            409,
            sprintf('Expected 409 Conflict. Response: %s', $this->getResponseContent())
        );
    }

    #[Then('registration should fail due to invalid email format')]
    public function registrationShouldFailDueToInvalidEmailFormat(): void
    {
        Assert::notNull($this->response, 'No response received');
        Assert::same(
            $this->response->getStatusCode(),
            422,
            sprintf('Expected 422 Unprocessable Entity. Response: %s', $this->getResponseContent())
        );
    }

    private function getResponseContent(): string
    {
        $content = $this->response?->getContent();

        if ($content === false || $content === null) {
            return 'No content';
        }

        return $content;
    }
}
