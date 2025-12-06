<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\MessageBus;

use App\Application\MessageBus\QueryInterface;
use App\Infrastructure\MessageBus\MessengerQueryBus;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Unit tests for MessengerQueryBus adapter.
 *
 * Tests the result extraction and exception unwrapping logic.
 *
 * @internal
 *
 * @covers \App\Infrastructure\MessageBus\MessengerQueryBus
 */
final class MessengerQueryBusTest extends TestCase
{
    /**
     * @var MessageBusInterface&MockObject
     */
    private MessageBusInterface $messengerBus;

    private MessengerQueryBus $queryBus;

    #[\Override]
    protected function setUp(): void
    {
        $this->messengerBus = $this->createMock(MessageBusInterface::class);
        $this->queryBus = new MessengerQueryBus($this->messengerBus);
    }

    public function testItDispatchesQueryAndReturnsResult(): void
    {
        // Arrange: Create a test query and expected result
        $query = $this->createTestQuery();
        $expectedResult = ['id' => 'user-123', 'name' => 'Test User'];

        // Create envelope with HandledStamp containing the result
        $envelope = new Envelope($query);
        $envelope = $envelope->with(new HandledStamp($expectedResult, 'TestHandler'));

        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($envelope)
        ;

        // Act
        $result = $this->queryBus->dispatch($query);

        // Assert
        self::assertSame($expectedResult, $result);
    }

    public function testItReturnsNullResultWhenHandlerReturnsNull(): void
    {
        // Arrange: Create a test query with null result
        $query = $this->createTestQuery();

        $envelope = new Envelope($query);
        $envelope = $envelope->with(new HandledStamp(null, 'TestHandler'));

        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willReturn($envelope)
        ;

        // Act
        $result = $this->queryBus->dispatch($query);

        // Assert
        self::assertNull($result);
    }

    public function testItUnwrapsSingleException(): void
    {
        // Arrange: Create a test query and a domain exception
        $query = $this->createTestQuery();
        $domainException = new \InvalidArgumentException('User not found');

        // Create HandlerFailedException with single wrapped exception
        $envelope = new Envelope($query);
        $handlerFailedException = new HandlerFailedException($envelope, [$domainException]);

        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willThrowException($handlerFailedException)
        ;

        // Assert: The domain exception should be unwrapped and re-thrown
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User not found');

        // Act
        $this->queryBus->dispatch($query);
    }

    public function testItPreservesHandlerFailedExceptionWithMultipleWrappedExceptions(): void
    {
        // Arrange: Create a test query and multiple exceptions
        $query = $this->createTestQuery();
        $exception1 = new \InvalidArgumentException('First error');
        $exception2 = new \RuntimeException('Second error');

        // Create HandlerFailedException with multiple wrapped exceptions
        $envelope = new Envelope($query);
        $handlerFailedException = new HandlerFailedException($envelope, [$exception1, $exception2]);

        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willThrowException($handlerFailedException)
        ;

        // Assert: The original HandlerFailedException should be thrown (not unwrapped)
        $this->expectException(HandlerFailedException::class);

        // Act
        $this->queryBus->dispatch($query);
    }

    public function testItPassesThroughNonHandlerFailedExceptions(): void
    {
        // Arrange: Messenger throws a different exception type
        $query = $this->createTestQuery();
        $transportException = new \RuntimeException('Connection failed');

        $this->messengerBus
            ->expects(self::once())
            ->method('dispatch')
            ->with($query)
            ->willThrowException($transportException)
        ;

        // Assert: The original exception should bubble through unchanged
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection failed');

        // Act
        $this->queryBus->dispatch($query);
    }

    /**
     * @return QueryInterface<mixed>
     */
    private function createTestQuery(): QueryInterface
    {
        return new class implements QueryInterface {
            public function __construct(
                public string $testData = 'test-value',
            ) {
            }
        };
    }
}
