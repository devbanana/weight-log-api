<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\PlainPassword;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Domain\User\ValueObject\PlainPassword
 *
 * @internal
 */
final class PlainPasswordTest extends TestCase
{
    #[DataProvider('provideItFailsWithInvalidStringCases')]
    public function testItFailsWithInvalidString(string $invalidPassword, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        PlainPassword::fromString($invalidPassword);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideItFailsWithInvalidStringCases(): iterable
    {
        yield 'empty string' => ['', 'Password cannot be empty'];

        yield 'only whitespace' => ['   ', 'Password cannot contain only whitespace'];

        yield 'too short' => ['Pass1!', 'Password must be at least 8 characters'];

        yield '7 characters' => ['Pass12!', 'Password must be at least 8 characters'];
    }

    #[DataProvider('provideItAcceptsValidPasswordsCases')]
    public function testItAcceptsValidPasswords(string $validPassword): void
    {
        $password = PlainPassword::fromString($validPassword);

        self::assertSame($validPassword, $password->asString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideItAcceptsValidPasswordsCases(): iterable
    {
        yield 'standard secure password' => ['SecurePass123!'];

        yield 'without special characters' => ['SecurePass123'];

        yield 'without numbers' => ['SecurePass!'];

        yield 'all lowercase' => ['securepass123!'];

        yield 'with multiple special chars' => ['P@ssw0rd!#$'];

        yield 'exactly 8 characters' => ['Pass123!'];

        yield 'long password' => ['ThisIsAVeryLongAndSecurePassword123!'];

        yield 'extremely long password' => ['P' . str_repeat('a', 1000) . 'ss123!'];

        yield 'with spaces' => ['My Pass 123!'];

        yield 'with various special characters' => ['Password1@'];

        yield 'with unicode characters' => ['Pässwörd123!'];
    }
}
