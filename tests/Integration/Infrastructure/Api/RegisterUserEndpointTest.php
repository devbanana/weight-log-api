<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Api;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\User\Command\RegisterUserCommand;
use App\Domain\User\Exception\UserAlreadyExistsException;
use App\Domain\User\ValueObject\Email;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

/**
 * Driving tests for the user registration API endpoint.
 *
 * These tests verify that the incoming HTTP adapter (API Platform processor)
 * correctly transforms HTTP requests into commands and dispatches them to the
 * application layer via the command bus.
 *
 * Following Matthias Noback's approach: These tests do NOT verify business logic
 * (that's covered by use case tests). They only verify HTTP → Command transformation
 * and proper error handling/response codes.
 *
 * @internal
 *
 * @covers \App\Infrastructure\Api\State\RegisterUserProcessor
 */
final class RegisterUserEndpointTest extends WebTestCase
{
    use HttpHelper;

    private KernelBrowser $client;

    /**
     * @var CommandBusInterface&MockObject
     */
    private CommandBusInterface $commandBus;

    #[\Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();

        // Mock the command bus to verify the processor dispatches the correct command
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        self::getContainer()->set(CommandBusInterface::class, $this->commandBus);
    }

    public function testItRegistersUserSuccessfully(): void
    {
        // Arrange: Expect command bus to be called with RegisterUserCommand
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (RegisterUserCommand $command): bool {
                // Verify command has valid UUID
                self::assertTrue(Uuid::isValid($command->userId), 'userId should be a valid UUID');

                // Verify command has correct email and password
                self::assertSame('test@example.com', $command->email);
                self::assertSame('SecurePass123!', $command->password);

                return true;
            }))
        ;

        // Act
        $this->postJson('/api/auth/register', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(201);
    }

    public function testItGeneratesUuidV7ForUserId(): void
    {
        // Arrange: Capture the generated user ID
        $capturedUserId = null;

        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (RegisterUserCommand $command) use (&$capturedUserId): bool {
                $capturedUserId = $command->userId;

                return true;
            }))
        ;

        // Act
        $this->postJson('/api/auth/register', [
            'email' => 'user@example.com',
            'password' => 'SecurePass123!',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(201);
        self::assertNotNull($capturedUserId);
        self::assertTrue(Uuid::isValid($capturedUserId), 'userId should be a valid UUID');
        self::assertInstanceOf(UuidV7::class, Uuid::fromString($capturedUserId), 'userId should be UUID v7 (time-ordered)');
    }

    public function testItPassesEmailToCommandWithoutNormalization(): void
    {
        // Arrange: Verify email is passed exactly as received
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (RegisterUserCommand $command): bool {
                // Email should be passed exactly as received, not normalized to lowercase
                self::assertSame('TEST@EXAMPLE.COM', $command->email);

                return true;
            }))
        ;

        // Act
        $this->postJson('/api/auth/register', [
            'email' => 'TEST@EXAMPLE.COM',
            'password' => 'SecurePass123!',
        ]);

        // Assert (normalization happens in domain layer)
        self::assertResponseStatusCodeSame(201);
    }

    public function testItReturns422ForInvalidEmailFormat(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'SecurePass123!',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('violations', $data);
        $violations = $data['violations'];
        assert(is_array($violations));
        self::assertCount(1, $violations);
        assert(is_array($violations[0]));
        self::assertSame('email', $violations[0]['propertyPath']);
    }

    public function testItReturns422ForEmptyEmail(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/auth/register', ['email' => '', 'password' => 'SecurePass123!']);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
    }

    public function testItReturns400ForMissingEmail(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/auth/register', ['password' => 'SecurePass123!']);

        // Assert (API Platform deserialization error)
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
    }

    public function testItReturns400ForMissingPassword(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/auth/register', ['email' => 'test@example.com']);

        // Assert (API Platform deserialization error)
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
    }

    public function testItReturns422ForPasswordTooShort(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/auth/register', ['email' => 'test@example.com', 'password' => 'short']);

        // Assert
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('violations', $data);
        $violations = $data['violations'];
        assert(is_array($violations));
        self::assertCount(1, $violations);
        assert(is_array($violations[0]));
        self::assertSame('password', $violations[0]['propertyPath']);
        self::assertSame('Password must be at least 8 characters long', $violations[0]['message']);
    }

    public function testItReturns409WhenUserAlreadyExists(): void
    {
        // Arrange
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(UserAlreadyExistsException::withEmail(
                Email::fromString('duplicate@example.com')
            ))
        ;

        // Act
        $this->postJson('/api/auth/register', [
            'email' => 'duplicate@example.com',
            'password' => 'SecurePass123!',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('title', $data);
        self::assertArrayHasKey('detail', $data);
        $detail = $data['detail'];
        assert(is_string($detail));
        self::assertStringContainsString('duplicate@example.com', $detail);
    }

    public function testItReturns400ForNonStringEmail(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act (type mismatch during deserialization)
        $this->postJson('/api/auth/register', ['email' => 12345]);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns400ForNullEmail(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act (type mismatch during deserialization)
        $this->postJson('/api/auth/register', ['email' => null]);

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns400ForMalformedJson(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/auth/register', '{invalid json}');

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns415ForWrongContentType(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act (form data instead of JSON)
        $this->client->request('POST', '/api/auth/register', ['email' => 'test@example.com']);

        // Assert
        self::assertResponseStatusCodeSame(415);
    }
}
