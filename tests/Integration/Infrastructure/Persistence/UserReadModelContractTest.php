<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\User\Event\UserRegistered;
use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Persistence\MongoDB\MongoUserReadModel;
use App\Infrastructure\Projection\UserProjection;
use App\Tests\UseCase\InMemoryUserReadModel;
use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for UserReadModel implementations.
 *
 * All implementations of UserReadModelInterface must pass these tests
 * to ensure consistent query behavior across adapters.
 *
 * @internal
 */
#[CoversClass(MongoUserReadModel::class)]
#[UsesClass(Email::class)]
#[UsesClass(UserProjection::class)]
final class UserReadModelContractTest extends TestCase
{
    #[DataProvider('readModelProvider')]
    public function testItReturnsFalseForNonExistentEmail(UserReadModelInterface $readModel): void
    {
        $email = Email::fromString('nonexistent@example.com');

        self::assertFalse($readModel->existsWithEmail($email));
    }

    #[DataProvider('readModelProvider')]
    public function testItReturnsNullForNonExistentEmailWhenFindingUserId(UserReadModelInterface $readModel): void
    {
        $email = Email::fromString('nonexistent@example.com');

        self::assertNull($readModel->findUserIdByEmail($email));
    }

    /**
     * Provides read model implementations for tests that don't need seeding.
     *
     * @return iterable<string, array{UserReadModelInterface}>
     */
    public static function readModelProvider(): iterable
    {
        yield 'InMemory' => [new InMemoryUserReadModel()];

        $mongoCollection = self::createMongoCollection();

        yield 'MongoDB' => [new MongoUserReadModel($mongoCollection)];
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('readModelWithSeederProvider')]
    public function testItReturnsTrueForExistingEmail(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $email = Email::fromString('exists@example.com');

        // Seed the read model with a registered user
        $seeder('exists@example.com');

        self::assertTrue($readModel->existsWithEmail($email));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('readModelWithSeederProvider')]
    public function testExistsWithEmailWorksWithMultipleUsers(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $seeder('user1@example.com');
        $seeder('user2@example.com');

        self::assertTrue($readModel->existsWithEmail(Email::fromString('user1@example.com')));
        self::assertTrue($readModel->existsWithEmail(Email::fromString('user2@example.com')));
        self::assertFalse($readModel->existsWithEmail(Email::fromString('user3@example.com')));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('readModelWithSeederProvider')]
    public function testItReturnsUserIdForExistingEmail(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $email = 'findbyemail@example.com';
        $expectedUserId = 'user-' . md5($email);

        $seeder($email);

        self::assertSame($expectedUserId, $readModel->findUserIdByEmail(Email::fromString($email)));
    }

    /**
     * @param callable(string): void $seeder
     */
    #[DataProvider('readModelWithSeederProvider')]
    public function testItReturnsCorrectUserIdWhenMultipleUsersExist(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $seeder('alice@example.com');
        $seeder('bob@example.com');

        $aliceId = $readModel->findUserIdByEmail(Email::fromString('alice@example.com'));
        $bobId = $readModel->findUserIdByEmail(Email::fromString('bob@example.com'));

        self::assertSame('user-' . md5('alice@example.com'), $aliceId);
        self::assertSame('user-' . md5('bob@example.com'), $bobId);
        self::assertNotSame($aliceId, $bobId);
    }

    /**
     * Provides read model implementations with their seeders.
     *
     * Each seeder is a closure that captures its dependencies. This design
     * ensures both implementations seed through their natural mechanisms:
     * - InMemory: via handleEvent() (same as production event handling)
     * - MongoDB: via UserProjection (mirrors production event projection)
     *
     * @return iterable<string, array{UserReadModelInterface, callable(string): void}>
     */
    public static function readModelWithSeederProvider(): iterable
    {
        // InMemory serves as reference implementation, validates test correctness,
        // and ensures the test double used in Behat use case tests behaves correctly
        $inMemoryReadModel = new InMemoryUserReadModel();

        yield 'InMemory' => [
            $inMemoryReadModel,
            self::createInMemorySeeder($inMemoryReadModel),
        ];

        $mongoCollection = self::createMongoCollection();

        yield 'MongoDB' => [
            new MongoUserReadModel($mongoCollection),
            self::createMongoSeeder($mongoCollection),
        ];
    }

    private static function createMongoCollection(): Collection
    {
        $mongoUrl = $_ENV['MONGODB_URL'];
        assert(is_string($mongoUrl), 'MONGODB_URL must be set in environment for tests');
        $database = $_ENV['MONGODB_DATABASE'];
        assert(is_string($database), 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);
        $collection = $client->selectCollection($database, 'users');
        $collection->drop();

        return $collection;
    }

    /**
     * Creates a seeder that populates the in-memory read model via event handling.
     *
     * @return callable(string): void
     */
    private static function createInMemorySeeder(InMemoryUserReadModel $readModel): callable
    {
        return static function (string $email) use ($readModel): void {
            $readModel->handleEvent(new UserRegistered(
                id: 'user-' . md5($email),
                email: $email,
                hashedPassword: 'hashed_password',
                occurredAt: new \DateTimeImmutable(),
            ));
        };
    }

    /**
     * Creates a seeder that populates MongoDB via the UserProjection.
     *
     * This mirrors production behavior where domain events flow through
     * the projection to update the read model.
     *
     * @return callable(string): void
     */
    private static function createMongoSeeder(Collection $collection): callable
    {
        $projection = new UserProjection($collection);

        return static function (string $email) use ($projection): void {
            $projection->onUserRegistered(new UserRegistered(
                id: 'user-' . md5($email),
                email: $email,
                hashedPassword: 'hashed_password',
                occurredAt: new \DateTimeImmutable(),
            ));
        };
    }
}
