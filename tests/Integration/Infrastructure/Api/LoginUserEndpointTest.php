<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Api;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\MessageBus\QueryBusInterface;
use App\Application\User\Command\LoginCommand;
use App\Application\User\Query\FindUserAuthDataByEmailQuery;
use App\Application\User\Query\UserAuthData;
use App\Domain\User\Exception\CouldNotAuthenticate;
use App\Infrastructure\Api\Resource\UserLoginResource;
use App\Infrastructure\Api\Resource\UserLoginResponse;
use App\Infrastructure\Api\State\LoginUserProcessor;
use App\Infrastructure\Security\SecurityUser;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Driving tests for the user login API endpoint.
 *
 * These tests verify that the incoming HTTP adapter (API Platform processor)
 * correctly transforms HTTP requests into queries/commands and generates JWT tokens.
 *
 * @internal
 */
#[CoversClass(UserLoginResource::class)]
#[CoversClass(LoginUserProcessor::class)]
#[CoversClass(UserLoginResponse::class)]
#[UsesClass(SecurityUser::class)]
final class LoginUserEndpointTest extends WebTestCase
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

    /**
     * @var JWTTokenManagerInterface&MockObject
     */
    private JWTTokenManagerInterface $jwtManager;

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

        // Mock the JWT manager for token generation
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        self::getContainer()->set(JWTTokenManagerInterface::class, $this->jwtManager);
    }

    public function testItLogsInUserSuccessfully(): void
    {
        // Arrange: Query returns user auth data
        $this->queryBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (FindUserAuthDataByEmailQuery $query): bool {
                self::assertSame('alice@example.com', $query->email);

                return true;
            }))
            ->willReturn(new UserAuthData('user-123'))
        ;

        // Arrange: Command bus dispatches login command
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (LoginCommand $command): bool {
                self::assertSame('user-123', $command->userId);
                self::assertSame('SecurePass123!', $command->password);

                return true;
            }))
        ;

        // Arrange: JWT manager creates token
        $this->jwtManager
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(static function (UserInterface $user): bool {
                self::assertSame('user-123', $user->getUserIdentifier());
                self::assertContains('ROLE_USER', $user->getRoles());

                return true;
            }))
            ->willReturn('jwt-token-123')
        ;

        // Act: POST to login endpoint
        $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
            'password' => 'SecurePass123!',
        ]);

        // Assert: Returns 200 OK with token
        self::assertResponseStatusCodeSame(200);

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('token', $data);
        self::assertSame('jwt-token-123', $data['token']);
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

        // Arrange: JWT manager should NOT be called
        $this->jwtManager
            ->expects(self::never())
            ->method('create')
        ;

        // Act: POST with non-existent email
        $this->postJson('/api/auth/login', [
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

        // Arrange: JWT manager should NOT be called
        $this->jwtManager
            ->expects(self::never())
            ->method('create')
        ;

        // Act: POST with wrong password
        $this->postJson('/api/auth/login', [
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
        $this->jwtManager->expects(self::never())->method('create');

        // Act: POST with invalid email
        $this->postJson('/api/auth/login', [
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
        $this->jwtManager->expects(self::never())->method('create');

        // Act: POST with empty email
        $this->postJson('/api/auth/login', [
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
        $this->jwtManager->expects(self::never())->method('create');

        // Act: POST with empty password
        $this->postJson('/api/auth/login', [
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
        $this->jwtManager->expects(self::never())->method('create');

        // Act: POST with no email field
        $this->postJson('/api/auth/login', [
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
        $this->jwtManager->expects(self::never())->method('create');

        // Act: POST with no password field
        $this->postJson('/api/auth/login', [
            'email' => 'alice@example.com',
        ]);

        // Assert: Returns 400 Bad Request
        self::assertResponseStatusCodeSame(400);
    }
}
