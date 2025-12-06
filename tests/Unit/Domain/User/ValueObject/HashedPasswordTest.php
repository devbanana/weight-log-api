<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\User\ValueObject;

use App\Domain\User\ValueObject\HashedPassword;
use App\Domain\User\ValueObject\PlainPassword;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    public function testItFailsWithEmptyHash(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hash cannot be empty');

        HashedPassword::fromHash('');
    }

    #[DataProvider('provideItVerifiesVariousPasswordsCases')]
    public function testItVerifiesVariousPasswords(string $original, string $attempt, bool $shouldMatch): void
    {
        $hash = password_hash($original, PASSWORD_DEFAULT);
        $hashedPassword = HashedPassword::fromHash($hash);
        $plainPassword = PlainPassword::fromString($attempt);

        self::assertSame($shouldMatch, $hashedPassword->verify($plainPassword));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideItVerifiesVariousPasswordsCases(): iterable
    {
        yield 'exact match' => ['SecurePass123!', 'SecurePass123!', true];

        yield 'case sensitive mismatch' => ['SecurePass123!', 'securepass123!', false];

        yield 'different password' => ['SecurePass123!', 'DifferentPass1!', false];

        yield 'password with spaces' => ['My Pass 123!', 'My Pass 123!', true];

        yield 'unicode password' => ['Pässwörd123!', 'Pässwörd123!', true];
    }

    public function testItReturnsHashAsString(): void
    {
        $hash = password_hash('TestPassword123!', PASSWORD_BCRYPT);
        $hashedPassword = HashedPassword::fromHash($hash);

        self::assertSame($hash, $hashedPassword->asString());
    }
}
