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

                // Verify command has correct email
                self::assertSame('test@example.com', $command->email);

                return true;
            }))
        ;

        // Act: POST to registration endpoint
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'test@example.com',
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 201 Created
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

        // Act: Make registration request
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'user@example.com',
        ], JSON_THROW_ON_ERROR));

        // Assert: User ID is a valid UUID v7
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

        // Act: POST with uppercase email
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'TEST@EXAMPLE.COM',
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 201 Created (normalization happens in domain layer)
        self::assertResponseStatusCodeSame(201);
    }

    public function testItReturns422ForInvalidEmailFormat(): void
    {
        // Arrange: Command bus should never be called for invalid input
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with invalid email
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'not-an-email',
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 422 Unprocessable Entity
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));

        self::assertArrayHasKey('violations', $data);
        $violations = $data['violations'];
        assert(is_array($violations));
        self::assertCount(1, $violations);
        assert(is_array($violations[0]));
        self::assertArrayHasKey('propertyPath', $violations[0]);
        self::assertSame('email', $violations[0]['propertyPath']);
    }

    public function testItReturns422ForEmptyEmail(): void
    {
        // Arrange: Command bus should never be called for invalid input
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with empty email
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => '',
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 422 Unprocessable Entity
        self::assertResponseStatusCodeSame(422);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
    }

    public function testItReturns400ForMissingEmail(): void
    {
        // Arrange: Command bus should never be called for invalid input
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with no email field
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            // No email field
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 400 Bad Request (API Platform deserialization error)
        self::assertResponseStatusCodeSame(400);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');
    }

    public function testItReturns409WhenUserAlreadyExists(): void
    {
        // Arrange: Command bus throws UserAlreadyExistsException
        $this->commandBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(UserAlreadyExistsException::withEmail(
                Email::fromString('duplicate@example.com')
            ))
        ;

        // Act: POST with email that already exists
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'duplicate@example.com',
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 409 Conflict
        self::assertResponseStatusCodeSame(409);
        self::assertResponseHeaderSame('Content-Type', 'application/problem+json; charset=utf-8');

        $content = $this->client->getResponse()->getContent();
        self::assertNotFalse($content);

        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        assert(is_array($data));

        // Verify error details
        self::assertArrayHasKey('title', $data);
        self::assertArrayHasKey('detail', $data);
        $detail = $data['detail'];
        assert(is_string($detail));
        self::assertStringContainsString('duplicate@example.com', $detail);
    }

    public function testItReturns400ForNonStringEmail(): void
    {
        // Arrange: Command bus should never be called for invalid input
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with integer as email
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 12345,
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 400 Bad Request (type mismatch during deserialization)
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns400ForNullEmail(): void
    {
        // Arrange: Command bus should never be called for invalid input
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with null as email
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => null,
        ], JSON_THROW_ON_ERROR));

        // Assert: Returns 400 Bad Request (type mismatch during deserialization)
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns400ForMalformedJson(): void
    {
        // Arrange: Command bus should never be called for malformed requests
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with malformed JSON
        $this->client->request('POST', '/api/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{invalid json}');

        // Assert: Returns 400 Bad Request
        self::assertResponseStatusCodeSame(400);
    }

    public function testItReturns415ForWrongContentType(): void
    {
        // Arrange: Command bus should never be called for wrong content type
        $this->commandBus
            ->expects(self::never())
            ->method('dispatch')
        ;

        // Act: POST with form data instead of JSON
        $this->client->request('POST', '/api/auth/register', [
            'email' => 'test@example.com',
        ]);

        // Assert: Returns 415 Unsupported Media Type
        self::assertResponseStatusCodeSame(415);
    }
}
