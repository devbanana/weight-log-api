<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User;

use App\Domain\User\Event\UserWasRegistered;
use App\Domain\User\User;
use App\Domain\User\ValueObject\Email;
use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\User\User
 *
 * @internal
 */
final class UserTest extends TestCase
{
    public function testItRecordsUserWasRegisteredEvent(): void
    {
        $userId = UserId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $email = Email::fromString('test@example.com');
        $registeredAt = new \DateTimeImmutable('2025-11-27 20:00:00', new \DateTimeZone('UTC'));

        $user = User::register($userId, $email, $registeredAt);

        $events = $user->releaseEvents();

        self::assertCount(1, $events);

        $event = $events[0];
        self::assertInstanceOf(UserWasRegistered::class, $event);
        self::assertSame('550e8400-e29b-41d4-a716-446655440000', $event->id);
        self::assertSame('test@example.com', $event->email);
        self::assertSame($registeredAt, $event->occurredAt);
    }
}
