<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\MessageBus;

use App\Application\MessageBus\CommandInterface;
use App\Infrastructure\MessageBus\MessengerCommandBus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit tests for MessengerCommandBus adapter.
 *
 * Tests the exception unwrapping logic that extracts domain exceptions
 * from Symfony Messenger's HandlerFailedException wrapper.
 *
 * @internal
 *
 * @covers \App\Infrastructure\MessageBus\MessengerCommandBus
 */
final class MessengerCommandBusTest extends TestCase
{
    /**
     * @var MessageBusInterface&MockObject
     */
    private MessageBusInterface $messengerBus;

    private MessengerCommandBus $commandBus;

    #[\Override]
    protected function setUp(): void
    {
        $this->messengerBus = $this->createMock(MessageBusInterface::class);
        $this->commandBus = new MessengerCommandBus($this->messengerBus);
    }

    public function testItDispatchesCommandSuccessfully(): void
    {
        // Arrange: Create a test command
        $command = $this->createTestCommand();

        // Mock messenger bus to succeed (no exception)
        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willReturn(new Envelope($command))
        ;

        // Act & Assert: No exception should be thrown
        $this->commandBus->dispatch($command);
    }

    public function testItUnwrapsSingleException(): void
    {
        // Arrange: Create a test command and a domain exception
        $command = $this->createTestCommand();
        $domainException = new \InvalidArgumentException('User already exists');

        // Create HandlerFailedException with single wrapped exception
        $envelope = new Envelope($command);
        $handlerFailedException = new HandlerFailedException($envelope, [$domainException]);

        // Mock messenger bus to throw HandlerFailedException
        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException($handlerFailedException)
        ;

        // Assert: The domain exception should be unwrapped and re-thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User already exists');

        // Act
        $this->commandBus->dispatch($command);
    }

    public function testItPreservesHandlerFailedExceptionWithMultipleWrappedExceptions(): void
    {
        // Arrange: Create a test command and multiple exceptions
        $command = $this->createTestCommand();
        $exception1 = new \InvalidArgumentException('First error');
        $exception2 = new \RuntimeException('Second error');

        // Create HandlerFailedException with multiple wrapped exceptions
        $envelope = new Envelope($command);
        $handlerFailedException = new HandlerFailedException($envelope, [$exception1, $exception2]);

        // Mock messenger bus to throw HandlerFailedException
        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($command)
            ->willThrowException($handlerFailedException)
        ;

        // Assert: The original HandlerFailedException should be thrown (not unwrapped)
        $this->expectException(HandlerFailedException::class);

        // Act
        $this->commandBus->dispatch($command);
    }

    private function createTestCommand(): CommandInterface
    {
        return new class implements CommandInterface {
            public function __construct(
                public string $testData = 'test-value',
            ) {
            }
        };
    }
}
