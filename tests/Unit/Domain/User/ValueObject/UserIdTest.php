<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\User\ValueObject\UserId
 *
 * @internal
 */
final class UserIdTest extends TestCase
{
    public function testItCreatesFromString(): void
    {
        $userId = UserId::fromString('123e4567-e89b-12d3-a456-426614174000');

        self::assertInstanceOf(UserId::class, $userId);
    }

    public function testItConvertsToString(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $userId = UserId::fromString($uuid);

        self::assertSame($uuid, $userId->asString());
    }

    public function testItRejectsInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not a valid UUID');

        UserId::fromString('not-a-uuid');
    }

    public function testItIsStringable(): void
    {
        $uuid = '123e4567-e89b-12d3-a456-426614174000';
        $userId = UserId::fromString($uuid);

        self::assertSame($uuid, (string) $userId);
        self::assertInstanceOf(\Stringable::class, $userId);
    }
}
