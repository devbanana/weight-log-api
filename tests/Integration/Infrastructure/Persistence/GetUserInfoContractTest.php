<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Application\User\Query\GetUserInfo;
use App\Application\User\Query\UserInfo;
use App\Domain\User\Event\UserRegistered;
use App\Domain\User\Exception\CouldNotFindUser;
use App\Domain\User\ValueObject\UserId;
use App\Infrastructure\Persistence\MongoDB\MongoGetUserInfo;
use App\Infrastructure\Projection\UserProjection;
use App\Tests\Integration\Infrastructure\MongoHelper;
use App\Tests\UseCase\InMemoryGetUserInfo;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Contract tests for GetUserInfo implementations.
 *
 * All implementations of GetUserInfo must pass these tests
 * to ensure consistent query behavior across adapters.
 *
 * @internal
 */
#[CoversClass(MongoGetUserInfo::class)]
#[UsesClass(UserId::class)]
#[UsesClass(UserProjection::class)]
final class GetUserInfoContractTest extends TestCase
{
    use MongoHelper;

    private const string TEST_DATE = '2025-01-15T10:30:00+00:00';

    #[\Override]
    public static function setUpBeforeClass(): void
    {
        self::getMongoDatabase()->selectCollection('users')->drop();
    }

    #[\Override]
    public static function tearDownAfterClass(): void
    {
        self::getMongoDatabase()->selectCollection('users')->drop();
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('getterWithSeederProvider')]
    public function testItThrowsExceptionForNonExistentUser(
        GetUserInfo $getter,
        callable $seeder,
    ): void {
        $seeder('existing@example.com');

        $this->expectException(CouldNotFindUser::class);

        $getter->byId(UserId::fromString(Uuid::v4()->toRfc4122()));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('getterWithSeederProvider')]
    public function testItReturnsUserInfoForExistingUser(
        GetUserInfo $getter,
        callable $seeder,
    ): void {
        $email = 'info@example.com';
        $expectedUserId = self::userIdFromEmail($email);

        $seeder($email);

        $result = $getter->byId(UserId::fromString($expectedUserId));

        self::assertInstanceOf(UserInfo::class, $result);
        self::assertSame($expectedUserId, $result->id);
        self::assertSame($email, $result->email);
        self::assertSame('Test User', $result->displayName);
        self::assertSame(self::TEST_DATE, $result->registeredAt);
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('getterWithSeederProvider')]
    public function testItReturnsCorrectUserWhenMultipleUsersExist(
        GetUserInfo $getter,
        callable $seeder,
    ): void {
        $seeder('alice@example.com');
        $seeder('bob@example.com');

        $alice = $getter->byId(UserId::fromString(self::userIdFromEmail('alice@example.com')));
        $bob = $getter->byId(UserId::fromString(self::userIdFromEmail('bob@example.com')));

        self::assertSame('alice@example.com', $alice->email);
        self::assertSame('bob@example.com', $bob->email);
    }

    /**
     * @return iterable<string, array{GetUserInfo, callable(string): void}>
     */
    public static function getterWithSeederProvider(): iterable
    {
        $inMemoryGetter = new InMemoryGetUserInfo();

        yield 'InMemory' => [
            $inMemoryGetter,
            self::createInMemorySeeder($inMemoryGetter),
        ];

        $mongoCollection = self::getMongoDatabase()->selectCollection('users');
        $mongoCollection->drop();

        yield 'MongoDB' => [
            new MongoGetUserInfo($mongoCollection),
            self::createMongoSeeder($mongoCollection),
        ];
    }

    /**
     * Generate a deterministic UUID from an email address.
     */
    private static function userIdFromEmail(string $email): string
    {
        return Uuid::v5(Uuid::fromString(Uuid::NAMESPACE_DNS), $email)->toRfc4122();
    }

    /**
     * @return callable(string): void
     */
    private static function createInMemorySeeder(InMemoryGetUserInfo $getter): callable
    {
        return static function (string $email) use ($getter): void {
            $getter->handleEvent(new UserRegistered(
                id: self::userIdFromEmail($email),
                email: $email,
                dateOfBirth: '1990-05-15',
                displayName: 'Test User',
                hashedPassword: 'hashed_password',
                occurredAt: new \DateTimeImmutable(self::TEST_DATE),
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
                id: self::userIdFromEmail($email),
                email: $email,
                dateOfBirth: '1990-05-15',
                displayName: 'Test User',
                hashedPassword: 'hashed_password',
                occurredAt: new \DateTimeImmutable(self::TEST_DATE),
            ));
        };
    }
}
