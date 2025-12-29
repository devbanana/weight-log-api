<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Hook\BeforeScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use MongoDB\Client;
use MongoDB\Database;
use Symfony\Component\Clock\MockClock;
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
    use HttpHelper;

    private ?Response $response = null;
    private Database $database;
    private ?string $authToken = null;

    /**
     * @var array<mixed, mixed>|null
     */
    private ?array $userInfo = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private MockClock $clock,
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

    #[Given('today\'s date is :date')]
    public function todaysDateIs(string $date): void
    {
        $dateTime = \DateTimeImmutable::createFromFormat('F j, Y H:i:s', $date . ' 12:00:00', new \DateTimeZone('UTC'));
        assert($dateTime instanceof \DateTimeImmutable);
        $this->clock->modify($dateTime->format('Y-m-d H:i:s'));
    }

    #[Given('a user exists with email :email')]
    public function aUserExistsWithEmail(string $email): void
    {
        $this->aUserExistsWithEmailAndPassword($email, 'DefaultPass123!');
    }

    #[Given('a user exists with email :email and password :password')]
    public function aUserExistsWithEmailAndPassword(string $email, string $password): void
    {
        $this->response = $this->makeJsonRequest('POST', '/api/users', [
            'email' => $email,
            'dateOfBirth' => '1990-01-01',
            'displayName' => 'Existing User',
            'password' => $password,
        ]);
        self::assertResponseStatusCode($this->response, 201, 'Created (test setup)');

        // Shutdown kernel to ensure services are fresh for next request
        $this->kernel->shutdown();
        $this->kernel->boot();

        $this->response = null;
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
    #[Then('registration should fail due to invalid date of birth')]
    #[Then('registration should fail due to invalid display name')]
    public function registrationShouldFailDueToInvalidPassword(): void
    {
        self::assertResponseStatusCode($this->response, 422, 'Unprocessable Entity');
    }

    #[Then('registration should fail because user is under 18')]
    public function registrationShouldFailBecauseUserIsUnder18(): void
    {
        self::assertResponseStatusCode($this->response, 422, 'Unprocessable Entity');
    }

    #[Then('registration should fail because date of birth is in the future')]
    public function registrationShouldFailBecauseDateOfBirthIsInTheFuture(): void
    {
        self::assertResponseStatusCode($this->response, 422, 'Unprocessable Entity');
    }

    #[When('I log in with email :email and password :password')]
    public function iLogInWithEmailAndPassword(string $email, string $password): void
    {
        $this->response = $this->makeJsonRequest('POST', '/api/tokens', [
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

    #[Given('a user registered with:')]
    public function aUserRegisteredWith(TableNode $table): void
    {
        $data = $table->getRowsHash();
        $email = $data['email'];
        $dateOfBirth = $data['dateOfBirth'];
        $displayName = $data['displayName'];
        $password = $data['password'];
        assert(is_string($email) && is_string($dateOfBirth) && is_string($displayName) && is_string($password));

        $this->registerWithData($email, $dateOfBirth, $displayName, $password);
        self::assertResponseStatusCode($this->response, 201, 'Created (test setup)');

        // Shutdown kernel to ensure services are fresh for next request
        $this->kernel->shutdown();
        $this->kernel->boot();

        $this->response = null;
    }

    #[Given('I am logged in as :email with password :password')]
    public function iAmLoggedInAsWithPassword(string $email, string $password): void
    {
        $this->iLogInWithEmailAndPassword($email, $password);
        self::assertResponseStatusCode($this->response, 200, 'OK');

        $content = self::getResponseContent($this->response);
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        assert(is_array($data) && isset($data['access_token']) && is_string($data['access_token']));

        $this->authToken = $data['access_token'];

        // Shutdown kernel to ensure services are fresh for next request
        $this->kernel->shutdown();
        $this->kernel->boot();
    }

    #[When('I request my user info')]
    public function iRequestMyUserInfo(): void
    {
        assert($this->authToken !== null, 'Must be logged in to request user info');

        $this->response = $this->makeGetRequest('/api/me', [
            'Authorization' => 'Bearer ' . $this->authToken,
        ]);

        if ($this->response->getStatusCode() === 200) {
            $content = self::getResponseContent($this->response);
            $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
            assert(is_array($data));
            $this->userInfo = $data;
        }
    }

    #[When('I request my user info without authentication')]
    public function iRequestMyUserInfoWithoutAuthentication(): void
    {
        $this->response = $this->makeGetRequest('/api/me');
    }

    #[Then('I should receive my user info with:')]
    public function iShouldReceiveMyUserInfoWith(TableNode $table): void
    {
        self::assertResponseStatusCode($this->response, 200, 'OK');
        assert($this->userInfo !== null, 'No user info received');

        $expected = $table->getRowsHash();

        if (isset($expected['email'])) {
            Assert::same($this->userInfo['email'], $expected['email']);
        }
        if (isset($expected['displayName'])) {
            Assert::same($this->userInfo['displayName'], $expected['displayName']);
        }
    }

    #[Then('my user info should include a valid registration timestamp')]
    public function myUserInfoShouldIncludeAValidRegistrationTimestamp(): void
    {
        assert($this->userInfo !== null, 'No user info received');
        Assert::keyExists($this->userInfo, 'registeredAt');
        Assert::string($this->userInfo['registeredAt']);

        $registeredAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $this->userInfo['registeredAt']);
        Assert::isInstanceOf($registeredAt, \DateTimeImmutable::class, 'registeredAt should be a valid ISO 8601 date');
    }

    #[Then('my user info should include my user ID')]
    public function myUserInfoShouldIncludeMyUserId(): void
    {
        assert($this->userInfo !== null, 'No user info received');
        Assert::keyExists($this->userInfo, 'id');
        Assert::string($this->userInfo['id']);
        Assert::notEmpty($this->userInfo['id']);
    }

    #[Then('I should receive a 401 Unauthorized error')]
    public function iShouldReceiveA401UnauthorizedError(): void
    {
        self::assertResponseStatusCode($this->response, 401, 'Unauthorized');
    }

    private function registerWithData(string $email, string $dateOfBirth, string $displayName, string $password): void
    {
        $this->response = $this->makeJsonRequest('POST', '/api/users', [
            'email' => $email,
            'dateOfBirth' => $dateOfBirth,
            'displayName' => $displayName,
            'password' => $password,
        ]);
    }
}
