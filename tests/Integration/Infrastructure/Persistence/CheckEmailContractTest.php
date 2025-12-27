<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Service\CheckEmail;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Persistence\MongoDB\MongoCheckEmail;
use App\Infrastructure\Projection\UserProjection;
use App\Tests\Integration\Infrastructure\MongoHelper;
use App\Tests\UseCase\InMemoryCheckEmail;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for CheckEmail implementations.
 *
 * All implementations of CheckEmail must pass these tests
 * to ensure consistent behavior across adapters.
 *
 * @internal
 */
#[CoversClass(MongoCheckEmail::class)]
#[UsesClass(Email::class)]
#[UsesClass(UserProjection::class)]
final class CheckEmailContractTest extends TestCase
{
    use MongoHelper;

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('provideCheckEmailWithSeederCases')]
    public function testItReturnsTrueWhenEmailIsUnique(
        CheckEmail $checkEmail,
        callable $seeder,
    ): void {
        $seeder('existing@example.com');

        self::assertTrue($checkEmail->isUnique(Email::fromString('different@example.com')));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('provideCheckEmailWithSeederCases')]
    public function testItReturnsFalseWhenEmailIsAlreadyTaken(
        CheckEmail $checkEmail,
        callable $seeder,
    ): void {
        $email = 'taken@example.com';
        $seeder($email);

        self::assertFalse($checkEmail->isUnique(Email::fromString($email)));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('provideCheckEmailWithSeederCases')]
    public function testItIsCaseInsensitive(
        CheckEmail $checkEmail,
        callable $seeder,
    ): void {
        $seeder('test@example.com');

        // Email value object normalizes to lowercase, so both should match
        self::assertFalse($checkEmail->isUnique(Email::fromString('TEST@EXAMPLE.COM')));
        self::assertFalse($checkEmail->isUnique(Email::fromString('Test@Example.Com')));
    }

    /**
     * @return iterable<string, array{CheckEmail, callable(string): void}>
     */
    public static function provideCheckEmailWithSeederCases(): iterable
    {
        $inMemoryCheckEmail = new InMemoryCheckEmail();

        yield 'InMemory' => [
            $inMemoryCheckEmail,
            self::createInMemorySeeder($inMemoryCheckEmail),
        ];

        $mongoCollection = self::getMongoDatabase()->selectCollection('users');
        $mongoCollection->drop();

        yield 'MongoDB' => [
            new MongoCheckEmail($mongoCollection),
            self::createMongoSeeder($mongoCollection),
        ];
    }

    /**
     * @return callable(string): void
     */
    private static function createInMemorySeeder(InMemoryCheckEmail $checkEmail): callable
    {
        return static function (string $email) use ($checkEmail): void {
            $checkEmail->handleEvent(new UserRegistered(
                id: 'user-' . md5($email),
                email: $email,
                dateOfBirth: '1990-05-15',
                displayName: 'Test User',
                hashedPassword: 'hashed_password',
                occurredAt: new \DateTimeImmutable(),
            ));
        };
    }

    /**
     * @return callable(string): void
     */
    private static function createMongoSeeder(Collection $collection): callable
    {
        $projection = new UserProjection($collection);

        return static function (string $email) use ($projection): void {
            $projection->onUserRegistered(new UserRegistered(
                id: 'user-' . md5($email),
                email: $email,
                dateOfBirth: '1990-05-15',
                displayName: 'Test User',
                hashedPassword: 'hashed_password',
                occurredAt: new \DateTimeImmutable(),
            ));
        };
    }
}
