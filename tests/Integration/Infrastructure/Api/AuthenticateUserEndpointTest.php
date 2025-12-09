<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Api;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\QueryBusInterface;
use App\Application\User\Command\LoginCommand;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;
use App\Application\User\Query\UserAuthData;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Infrastructure\Api\Resource\UserAuthenticationResource;
use App\Infrastructure\Api\Resource\UserAuthenticationResponse;
use App\Infrastructure\Api\State\AuthenticateUserProcessor;
use App\Infrastructure\Security\SecurityUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Driving tests for the user authentication API endpoint.
 *
 * These tests verify that the incoming HTTP adapter (API Platform processor)
 * correctly transforms HTTP requests into queries/commands and generates JWT tokens.
 *
 * Note: Uses real JWT service (not mocked) to verify actual token generation.
 *
 * @internal
 */
#[CoversClass(UserAuthenticationResource::class)]
#[CoversClass(AuthenticateUserProcessor::class)]
#[CoversClass(UserAuthenticationResponse::class)]
#[UsesClass(SecurityUser::class)]
final class AuthenticateUserEndpointTest extends WebTestCase
{
    use HttpHelper;

    private KernelBrowser $client;

    /**
     * @var MockObject&QueryBusInterface
     */
    private QueryBusInterface $queryBus;

    /**
     * @var CommandBusInterface&MockObject
     */
    private CommandBusInterface $commandBus;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();

        // Mock the query bus for looking up user auth data
        $this->queryBus = $this->createMock(QueryBusInterface::class);
        self::getContainer()->set(QueryBusInterface::class, $this->queryBus);

        // Mock the command bus for dispatching login command
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        self::getContainer()->set(CommandBusInterface::class, $this->commandBus);
    }

    public function testItAuthenticatesUserSuccessfully(): void
    {
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (FindUserAuthDataByEmailQuery $query): bool {
                self::assertSame('alice@example.com', $query->email);

                return true;
            }))
            ->willReturn(new UserAuthData('user-123'))
        ;

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (LoginCommand $command): bool {
                self::assertSame('user-123', $command->userId);
                self::assertSame('SecurePass123!', $command->password);

                return true;
            }))
        ;

        $this->postJson('/api/tokens', [
            'email' => 'alice@example.com',
            'password' => 'SecurePass123!',
        ]);

        self::assertResponseStatusCodeSame(200);

        $data = $this->getJsonResponse();

        self::assertArrayHasKey('access_token', $data);
        self::assertIsString($data['access_token']);
        self::assertNotEmpty($data['access_token']);

        self::assertArrayHasKey('token_type', $data);
        self::assertSame('Bearer', $data['token_type']);

        self::assertArrayHasKey('expires_in', $data);
        self::assertSame(3_600, $data['expires_in']);

        self::assertArrayHasKey('expires_at', $data);
        self::assertIsString($data['expires_at']);
        $expiresAtDate = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $data['expires_at']);
        self::assertInstanceOf(\DateTimeImmutable::class, $expiresAtDate);
        self::assertSame('+00:00', $expiresAtDate->format('P'), 'expires_at should be in UTC');

        $expectedExpiresAt = new \DateTimeImmutable()->modify('+3600 seconds');
        self::assertEqualsWithDelta(
            $expectedExpiresAt->getTimestamp(),
            $expiresAtDate->getTimestamp(),
            5,
        );
    }

    public function testItReturns401WhenUserNotFound(): void
    {
        // Arrange: Query returns null (user not found)
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn(null)
        ;

        // Arrange: Command bus should NOT be called
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with non-existent email
        $this->postJson('/api/tokens', [
            'email' => 'nonexistent@example.com',
            'password' => 'SecurePass123!',
        ]);

        // Assert: Returns 401 Unauthorized
        self::assertResponseStatusCodeSame(401);
    }

    public function testItReturns401WhenPasswordIsWrong(): void
    {
        // Arrange: Query returns user auth data
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->willReturn(new UserAuthData('user-123'))
        ;

        // Arrange: Command bus throws CouldNotAuthenticate
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(CouldNotAuthenticate::becauseInvalidCredentials())
        ;

        // Act: POST with wrong password
        $this->postJson('/api/tokens', [
            'email' => 'alice@example.com',
            'password' => 'WrongPassword!',
        ]);

        // Assert: Returns 401 Unauthorized
        self::assertResponseStatusCodeSame(401);
    }

    public function testItReturns422ForInvalidEmailFormat(): void
    {
        // Arrange: No services should be called for invalid input
        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act: POST with invalid email
        $this->postJson('/api/tokens', [
            'email' => 'not-an-email',
            'password' => 'SecurePass123!',
        ]);

        // Assert: Returns 422 Unprocessable Entity
        self::assertResponseStatusCodeSame(422);
    }

    public function testItReturns422ForEmptyEmail(): void
    {
        // Arrange: No services should be called for invalid input
        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act: POST with empty email
        $this->postJson('/api/tokens', [
            'email' => '',
            'password' => 'SecurePass123!',
        ]);

        // Assert: Returns 422 Unprocessable Entity
        self::assertResponseStatusCodeSame(422);
    }

    public function testItReturns422ForEmptyPassword(): void
    {
        // Arrange: No services should be called for invalid input
        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act: POST with empty password
        $this->postJson('/api/tokens', [
            'email' => 'alice@example.com',
            'password' => '',
        ]);

        // Assert: Returns 422 Unprocessable Entity
        self::assertResponseStatusCodeSame(422);
    }

    public function testItReturns400ForMissingEmail(): void
    {
        // Arrange: No services should be called for invalid input
        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act: POST with no email field
        $this->postJson('/api/tokens', [
            'password' => 'SecurePass123!',
        ]);

        // Assert: Returns 400 Bad Request
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns400ForMissingPassword(): void
    {
        // Arrange: No services should be called for invalid input
        $this->queryBus->expects(self::never())->method('dispatch');
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act: POST with no password field
        $this->postJson('/api/tokens', [
            'email' => 'alice@example.com',
        ]);

        // Assert: Returns 400 Bad Request
        self::assertResponseStatusCodeSame(400);
    }
}
