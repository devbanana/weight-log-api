<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Security;

use App\Application\Security\PasswordHasherInterface;
use App\Domain\User\ValueObject\PlainPassword;
use App\Infrastructure\Security\NativePasswordHasher;
use App\Tests\UseCase\FakePasswordHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for PasswordHasher implementations.
 *
 * All implementations of PasswordHasherInterface must pass these tests
 * to ensure consistent hashing behavior across adapters.
 *
 * @covers \App\Infrastructure\Security\NativePasswordHasher
 * @covers \App\Tests\UseCase\FakePasswordHasher
 *
 * @internal
 */
final class PasswordHasherContractTest extends TestCase
{
    #[DataProvider('hasherProvider')]
    public function testItHashesPassword(PasswordHasherInterface $hasher): void
    {
        $plainPassword = PlainPassword::fromString('SecurePass123!');

        $hashedPassword = $hasher->hash($plainPassword);

        self::assertNotEmpty($hashedPassword->asString());
    }

    #[DataProvider('hasherProvider')]
    public function testItProducesVerifiableHash(PasswordHasherInterface $hasher): void
    {
        $plainPassword = PlainPassword::fromString('SecurePass123!');

        $hashedPassword = $hasher->hash($plainPassword);

        self::assertTrue($hashedPassword->verify($plainPassword));
    }

    #[DataProvider('hasherProvider')]
    public function testHashedPasswordDoesNotMatchDifferentPassword(PasswordHasherInterface $hasher): void
    {
        $original = PlainPassword::fromString('SecurePass123!');
        $different = PlainPassword::fromString('DifferentPass456!');

        $hashedPassword = $hasher->hash($original);

        self::assertFalse($hashedPassword->verify($different));
    }

    #[DataProvider('hasherProvider')]
    public function testItProducesDifferentHashesForSamePassword(PasswordHasherInterface $hasher): void
    {
        $password = PlainPassword::fromString('SecurePass123!');

        $hash1 = $hasher->hash($password);
        $hash2 = $hasher->hash($password);

        // Hashes should differ due to random salt
        self::assertNotSame($hash1->asString(), $hash2->asString());

        // But both should verify the same password
        self::assertTrue($hash1->verify($password));
        self::assertTrue($hash2->verify($password));
    }

    #[DataProvider('hasherProvider')]
    public function testItHandlesSpecialCharactersInPassword(PasswordHasherInterface $hasher): void
    {
        $password = PlainPassword::fromString('P@$$w0rd!#%^&*()_+-=[]{}|;:,.<>?');

        $hashedPassword = $hasher->hash($password);

        self::assertTrue($hashedPassword->verify($password));
    }

    #[DataProvider('hasherProvider')]
    public function testItHandlesUnicodeCharactersInPassword(PasswordHasherInterface $hasher): void
    {
        $password = PlainPassword::fromString('Pässwörd123!');

        $hashedPassword = $hasher->hash($password);

        self::assertTrue($hashedPassword->verify($password));
    }

    #[DataProvider('hasherProvider')]
    public function testItHandlesLongPassword(PasswordHasherInterface $hasher): void
    {
        // Use 64 characters to stay under bcrypt's 72-byte limit while still being "long"
        $password = PlainPassword::fromString(str_repeat('a', 56) . '12345678');

        $hashedPassword = $hasher->hash($password);

        self::assertTrue($hashedPassword->verify($password));
    }

    #[DataProvider('hasherProvider')]
    public function testItHandlesMinimumLengthPassword(PasswordHasherInterface $hasher): void
    {
        // Exactly 8 characters - the minimum allowed by PlainPassword
        $password = PlainPassword::fromString('12345678');

        $hashedPassword = $hasher->hash($password);

        self::assertTrue($hashedPassword->verify($password));
    }

    /**
     * @return \Generator<string, array{PasswordHasherInterface}>
     */
    public static function hasherProvider(): iterable
    {
        // FakePasswordHasher serves as reference implementation and validates
        // test correctness. It's also used in Behat use case tests.
        yield 'FakePasswordHasher' => [new FakePasswordHasher()];

        yield 'NativePasswordHasher' => [new NativePasswordHasher()];
    }
}
