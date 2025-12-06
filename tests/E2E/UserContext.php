<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Behat\Behat\Context\Context;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use MongoDB\Client;
use MongoDB\Database;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * End-to-end context tests the full application stack with real HTTP requests.
 * This tests the complete system including API Platform, MongoDB, etc.
 *
 * @internal
 */
final class UserContext implements Context
{
    use HttpHelper;

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
    public function cleanDatabase(): void
    {
        $this->database->dropCollection('events');
        $this->database->dropCollection('users');
    }

    #[Given('a user exists with email :email and password :password')]
    public function aUserExistsWithEmailAndPassword(string $email, string $password): void
    {
        $this->iRegisterWithEmailAndPassword($email, $password);
        self::assertResponseStatusCode($this->response, 201, 'Created (test setup)');

        // Shutdown kernel to ensure services are fresh for next request
        $this->kernel->shutdown();
        $this->kernel->boot();

        $this->response = null;
    }

    #[When('I register with email :email and password :password')]
    public function iRegisterWithEmailAndPassword(string $email, string $password): void
    {
        $this->response = $this->makeJsonRequest('POST', '/api/auth/register', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    #[Then('I should be registered')]
    public function iShouldBeRegistered(): void
    {
        self::assertResponseStatusCode($this->response, 201, 'Created');
    }

    #[Then('registration should fail due to duplicate email')]
    public function registrationShouldFailDueToDuplicateEmail(): void
    {
        self::assertResponseStatusCode($this->response, 409, 'Conflict');
    }

    #[Then('registration should fail due to invalid email format')]
    public function registrationShouldFailDueToInvalidEmailFormat(): void
    {
        self::assertResponseStatusCode($this->response, 422, 'Unprocessable Entity');
    }

    #[Then('registration should fail due to invalid password')]
    public function registrationShouldFailDueToInvalidPassword(): void
    {
        self::assertResponseStatusCode($this->response, 422, 'Unprocessable Entity');
    }

    #[When('I log in with email :email and password :password')]
    public function iLogInWithEmailAndPassword(string $email, string $password): void
    {
        $this->response = $this->makeJsonRequest('POST', '/api/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }

    #[Then('I should be logged in')]
    #[Then('I should receive an authentication token')]
    public function iShouldBeLoggedIn(): void
    {
        self::assertResponseStatusCode($this->response, 200, 'OK');
        self::assertResponseContainsToken($this->response);
    }

    #[Then('login should fail due to invalid credentials')]
    public function loginShouldFailDueToInvalidCredentials(): void
    {
        self::assertResponseStatusCode($this->response, 401, 'Unauthorized');
    }

    #[Then('login should fail due to invalid email format')]
    #[Then('login should fail due to invalid password')]
    public function loginShouldFailDueToValidationError(): void
    {
        self::assertResponseStatusCode($this->response, 422, 'Unprocessable Entity');
    }
}
