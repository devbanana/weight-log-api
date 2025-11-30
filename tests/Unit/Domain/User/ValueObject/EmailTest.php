<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\Email;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\User\ValueObject\Email
 *
 * @internal
 */
final class EmailTest extends TestCase
{
    public function testItCreatesEmailFromValidString(): void
    {
        $email = Email::fromString('test@example.com');

        self::assertSame('test@example.com', $email->asString());
    }

    public function testItNormalizesEmailToLowercase(): void
    {
        $email = Email::fromString('TEST@Example.COM');

        self::assertSame('test@example.com', $email->asString());
    }

    public function testItTrimsWhitespace(): void
    {
        $email = Email::fromString('  test@example.com  ');

        self::assertSame('test@example.com', $email->asString());
    }

    public function testItRejectsInvalidEmailFormat(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        Email::fromString('not-an-email');
    }

    public function testItRejectsEmptyEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email cannot be empty');

        Email::fromString('');
    }

    public function testItRejectsEmailWithoutDomain(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        Email::fromString('test@');
    }

    public function testItIsStringable(): void
    {
        $emailAddress = 'test@example.com';
        $email = Email::fromString($emailAddress);

        self::assertSame($emailAddress, (string) $email);
        self::assertInstanceOf(\Stringable::class, $email);
    }
}
