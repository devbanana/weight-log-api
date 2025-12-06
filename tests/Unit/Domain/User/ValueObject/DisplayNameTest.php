<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\DisplayName;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DisplayName::class)]
final class DisplayNameTest extends TestCase
{
    public function testItCanBeConvertedFromValidString(): void
    {
        $displayName = DisplayName::fromString('John Doe');

        self::assertInstanceOf(DisplayName::class, $displayName);
    }

    #[DataProvider('provideItFailsWithInvalidStringCases')]
    public function testItFailsWithInvalidString(string $invalidName, string $expectedMessage): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        DisplayName::fromString($invalidName);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideItFailsWithInvalidStringCases(): iterable
    {
        yield 'empty string' => ['', 'Display name cannot be empty'];

        yield 'only whitespace' => ['   ', 'Display name cannot be empty'];

        yield 'exceeds 50 characters' => [
            str_repeat('a', 51),
            'Display name cannot exceed 50 characters',
        ];
    }

    #[DataProvider('provideItAcceptsValidDisplayNamesCases')]
    public function testItAcceptsValidDisplayNames(string $validName): void
    {
        $displayName = DisplayName::fromString($validName);

        self::assertInstanceOf(DisplayName::class, $displayName);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideItAcceptsValidDisplayNamesCases(): iterable
    {
        yield 'simple name' => ['John'];

        yield 'full name' => ['John Doe'];

        yield 'name with middle initial' => ['John Q. Public'];

        yield 'exactly 50 characters' => [str_repeat('a', 50)];

        yield 'name with special characters' => ["O'Brien-Smith"];

        yield 'name with accents' => ['José García'];

        yield 'name in another alphabet' => ['Иван Иванович'];

        yield '50 characters in another alphabet' => [str_repeat('あ', 50)];
    }
}
