<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Application\User\Query\FindUserAuthData;
use App\Application\User\Query\UserAuthData;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Persistence\MongoDB\MongoFindUserAuthData;
use App\Infrastructure\Projection\UserProjection;
use App\Tests\Integration\Infrastructure\MongoHelper;
use App\Tests\UseCase\InMemoryFindUserAuthData;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for FindUserAuthData implementations.
 *
 * All implementations of FindUserAuthData must pass these tests
 * to ensure consistent query behavior across adapters.
 *
 * @internal
 */
#[CoversClass(MongoFindUserAuthData::class)]
#[UsesClass(Email::class)]
#[UsesClass(UserProjection::class)]
final class FindUserAuthDataContractTest extends TestCase
{
    use MongoHelper;

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('finderWithSeederProvider')]
    public function testItReturnsNullForNonExistentEmail(
        FindUserAuthData $finder,
        callable $seeder,
    ): void {
        $seeder('existing@example.com');

        self::assertNull($finder->byEmail(Email::fromString('nonexistent@example.com')));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('finderWithSeederProvider')]
    public function testItReturnsUserAuthDataForExistingEmail(
        FindUserAuthData $finder,
        callable $seeder,
    ): void {
        $email = 'auth@example.com';
        $expectedUserId = 'user-' . md5($email);

        $seeder($email);

        $result = $finder->byEmail(Email::fromString($email));

        self::assertInstanceOf(UserAuthData::class, $result);
        self::assertSame($expectedUserId, $result->userId);
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('finderWithSeederProvider')]
    public function testItReturnsDefaultRoles(
        FindUserAuthData $finder,
        callable $seeder,
    ): void {
        $email = 'roles@example.com';

        $seeder($email);

        $result = $finder->byEmail(Email::fromString($email));

        self::assertNotNull($result);
        self::assertSame(['ROLE_USER'], $result->roles);
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('finderWithSeederProvider')]
    public function testItReturnsCorrectUserWhenMultipleUsersExist(
        FindUserAuthData $finder,
        callable $seeder,
    ): void {
        $seeder('alice@example.com');
        $seeder('bob@example.com');

        $alice = $finder->byEmail(Email::fromString('alice@example.com'));
        $bob = $finder->byEmail(Email::fromString('bob@example.com'));

        self::assertNotNull($alice);
        self::assertNotNull($bob);
        self::assertSame('user-' . md5('alice@example.com'), $alice->userId);
        self::assertSame('user-' . md5('bob@example.com'), $bob->userId);
        self::assertNotSame($alice->userId, $bob->userId);
    }

    /**
     * @return iterable<string, array{FindUserAuthData, callable(string): void}>
     */
    public static function finderWithSeederProvider(): iterable
    {
        $inMemoryFinder = new InMemoryFindUserAuthData();

        yield 'InMemory' => [
            $inMemoryFinder,
            self::createInMemorySeeder($inMemoryFinder),
        ];

        $mongoCollection = self::getMongoDatabase()->selectCollection('users');
        $mongoCollection->drop();

        yield 'MongoDB' => [
            new MongoFindUserAuthData($mongoCollection),
            self::createMongoSeeder($mongoCollection),
        ];
    }

    /**
     * @return callable(string): void
     */
    private static function createInMemorySeeder(InMemoryFindUserAuthData $finder): callable
    {
        return static function (string $email) use ($finder): void {
            $finder->handleEvent(new UserRegistered(
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
