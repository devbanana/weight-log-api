<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Api;

use App\Application\MessageBus\CommandBusInterface;
use App\Application\User\Command\RegisterUserCommand;
use App\Domain\User\Exception\CouldNotRegister;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Api\EventListener\TokenResponseHeadersListener;
use App\Infrastructure\Api\Resource\UserRegistrationResource;
use App\Infrastructure\Api\State\RegisterUserProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
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
 */
#[CoversClass(RegisterUserProcessor::class)]
#[CoversClass(UserRegistrationResource::class)]
#[UsesClass(Email::class)]
#[UsesClass(TokenResponseHeadersListener::class)]
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

                // Verify command has correct fields
                self::assertSame('test@example.com', $command->email);
                self::assertSame('1990-05-15', $command->dateOfBirth);
                self::assertSame('Test User', $command->displayName);
                self::assertSame('SecurePass123!', $command->password);

                return true;
            }))
        ;

        // Act
        $this->postJson('/api/users', [
            'email' => 'test@example.com',
            'dateOfBirth' => '1990-05-15',
            'displayName' => 'Test User',
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
        $this->postJson('/api/users', [
            'email' => 'user@example.com',
            'dateOfBirth' => '1990-05-15',
            'displayName' => 'Test User',
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
        $this->postJson('/api/users', [
            'email' => 'TEST@EXAMPLE.COM',
            'dateOfBirth' => '1990-05-15',
            'displayName' => 'Test User',
            'password' => 'SecurePass123!',
        ]);

        // Assert (normalization happens in domain layer)
        self::assertResponseStatusCodeSame(201);
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideItReturns422ForValidationErrorsCases')]
    public function testItReturns422ForValidationErrors(
        array $payload,
        string $expectedPath,
        ?string $expectedMessage = null,
    ): void {
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->postJson('/api/users', $payload);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('violations', $data);
        $violations = $data['violations'];
        self::assertIsArray($violations);
        self::assertCount(1, $violations);
        self::assertIsArray($violations[0]);
        self::assertSame($expectedPath, $violations[0]['propertyPath']);

        if ($expectedMessage !== null) {
            self::assertSame($expectedMessage, $violations[0]['message']);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, 2?: string}>
     */
    public static function provideItReturns422ForValidationErrorsCases(): iterable
    {
        $valid = [
            'email' => 'test@example.com',
            'dateOfBirth' => '1990-05-15',
            'displayName' => 'Test User',
            'password' => 'SecurePass123!',
        ];

        yield 'invalid email format' => [[...$valid, 'email' => 'not-an-email'], 'email'];

        yield 'empty email' => [[...$valid, 'email' => ''], 'email'];

        yield 'password too short' => [
            [...$valid, 'password' => 'short'],
            'password',
            'Password must be at least 8 characters long',
        ];

        yield 'empty display name' => [[...$valid, 'displayName' => ''], 'displayName'];

        yield 'whitespace-only display name' => [[...$valid, 'displayName' => '   '], 'displayName'];

        yield 'display name too long' => [
            [...$valid, 'displayName' => str_repeat('a', 51)],
            'displayName',
            'Display name cannot exceed 50 characters',
        ];

        yield 'empty date of birth' => [[...$valid, 'dateOfBirth' => ''], 'dateOfBirth'];

        yield 'invalid date of birth format' => [[...$valid, 'dateOfBirth' => 'invalid'], 'dateOfBirth'];

        yield 'date of birth with time component' => [[...$valid, 'dateOfBirth' => '1990-05-15T12:00:00'], 'dateOfBirth'];
    }

    /**
     * Tests for 400 errors from API Platform deserialization failures.
     *
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideItReturns400ForDeserializationErrorsCases')]
    public function testItReturns400ForDeserializationErrors(array $payload): void
    {
        $this->commandBus->expects(self::never())->method('dispatch');

        $this->postJson('/api/users', $payload);

        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function provideItReturns400ForDeserializationErrorsCases(): iterable
    {
        $valid = [
            'email' => 'test@example.com',
            'dateOfBirth' => '1990-05-15',
            'displayName' => 'Test User',
            'password' => 'SecurePass123!',
        ];

        // Missing required fields
        yield 'missing email' => [array_diff_key($valid, ['email' => true])];

        yield 'missing password' => [array_diff_key($valid, ['password' => true])];

        yield 'missing displayName' => [array_diff_key($valid, ['displayName' => true])];

        yield 'missing dateOfBirth' => [array_diff_key($valid, ['dateOfBirth' => true])];

        // Wrong types
        yield 'non-string email' => [[...$valid, 'email' => 12_345]];

        yield 'null email' => [[...$valid, 'email' => null]];
    }

    public function testItReturns409WhenUserAlreadyExists(): void
    {
        // Arrange
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(CouldNotRegister::becauseEmailIsAlreadyInUse(
                Email::fromString('duplicate@example.com'),
            ))
        ;

        // Act
        $this->postJson('/api/users', [
            'email' => 'duplicate@example.com',
            'dateOfBirth' => '1990-05-15',
            'displayName' => 'Test User',
            'password' => 'SecurePass123!',
        ]);

        // Assert
        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('title', $data);
        self::assertArrayHasKey('detail', $data);
        $detail = $data['detail'];
        self::assertIsString($detail);
        self::assertStringContainsString('duplicate@example.com', $detail);
    }

    public function testItReturns422WhenUserIsTooYoung(): void
    {
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(CouldNotRegister::becauseUserIsTooYoung(16, 18))
        ;

        $this->postJson('/api/users', [
            'email' => 'young@example.com',
            'dateOfBirth' => '2010-06-15',
            'displayName' => 'Young User',
            'password' => 'SecurePass123!',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('detail', $data);
        $detail = $data['detail'];
        self::assertIsString($detail);
        self::assertStringContainsString('at least 18 years old', $detail);
    }

    public function testItReturns422WhenDateOfBirthIsInTheFuture(): void
    {
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(CouldNotRegister::becauseDateOfBirthIsInTheFuture())
        ;

        $this->postJson('/api/users', [
            'email' => 'future@example.com',
            'dateOfBirth' => '2030-01-01',
            'displayName' => 'Future User',
            'password' => 'SecurePass123!',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $data = $this->getJsonResponse();
        self::assertArrayHasKey('detail', $data);
        $detail = $data['detail'];
        self::assertIsString($detail);
        self::assertStringContainsString('cannot be in the future', $detail);
    }

    public function testItReturns400ForMalformedJson(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act
        $this->postJson('/api/users', '{invalid json}');

        // Assert
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns415ForWrongContentType(): void
    {
        // Arrange
        $this->commandBus->expects(self::never())->method('dispatch');

        // Act (form data instead of JSON)
        $this->client->request('POST', '/api/users', ['email' => 'test@example.com']);

        // Assert
        self::assertResponseStatusCodeSame(415);
    }
}
