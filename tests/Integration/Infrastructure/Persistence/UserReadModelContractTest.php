<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\User\Event\UserRegistered;
use App\Domain\User\UserReadModelInterface;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Persistence\MongoDB\MongoUserReadModel;
use App\Tests\UseCase\InMemoryUserReadModel;
use MongoDB\Client;
use MongoDB\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for UserReadModel implementations.
 *
 * All implementations of UserReadModelInterface must pass these tests
 * to ensure consistent query behavior across adapters.
 *
 * @covers \App\Infrastructure\Persistence\MongoDB\MongoUserReadModel
 * @covers \App\Tests\UseCase\InMemoryUserReadModel
 *
 * @internal
 */
final class UserReadModelContractTest extends TestCase
{
    private static ?Collection $mongoCollection = null;

    /**
     * @param callable(UserReadModelInterface, string): void $seeder
     */
    #[DataProvider('readModelProvider')]
    public function testItReturnsFalseForNonExistentEmail(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $email = Email::fromString('nonexistent@example.com');

        self::assertFalse($readModel->existsWithEmail($email));
    }

    /**
     * @param callable(UserReadModelInterface, string): void $seeder
     */
    #[DataProvider('readModelProvider')]
    public function testItReturnsTrueForExistingEmail(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $email = Email::fromString('exists@example.com');

        // Seed the read model with a registered user
        $seeder($readModel, 'exists@example.com');

        self::assertTrue($readModel->existsWithEmail($email));
    }

    /**
     * @param callable(UserReadModelInterface, string): void $seeder
     */
    #[DataProvider('readModelProvider')]
    public function testItHandlesMultipleUsers(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $seeder($readModel, 'user1@example.com');
        $seeder($readModel, 'user2@example.com');

        self::assertTrue($readModel->existsWithEmail(Email::fromString('user1@example.com')));
        self::assertTrue($readModel->existsWithEmail(Email::fromString('user2@example.com')));
        self::assertFalse($readModel->existsWithEmail(Email::fromString('user3@example.com')));
    }

    /**
     * @param callable(UserReadModelInterface, string): void $seeder
     */
    #[DataProvider('readModelProvider')]
    public function testItMatchesEmailCaseInsensitively(
        UserReadModelInterface $readModel,
        callable $seeder,
    ): void {
        $seeder($readModel, 'Test@Example.COM');

        // Email value object normalizes to lowercase, so this should match
        self::assertTrue($readModel->existsWithEmail(Email::fromString('test@example.com')));
    }

    /**
     * Provides read model implementations with their seeders.
     *
     * The seeder callable takes the read model and an email string,
     * and populates the read model with a user having that email.
     *
     * @return \Generator<string, array{UserReadModelInterface, callable(UserReadModelInterface, string): void}>
     */
    public static function readModelProvider(): iterable
    {
        // InMemory serves as reference implementation, validates test correctness,
        // and ensures the test double used in Behat use case tests behaves correctly
        yield 'InMemory' => [
            new InMemoryUserReadModel(),
            self::inMemorySeeder(...),
        ];

        yield 'MongoDB' => [
            self::createMongoUserReadModel(),
            self::mongoSeeder(...),
        ];
    }

    private static function createMongoUserReadModel(): MongoUserReadModel
    {
        $mongoUrl = $_ENV['MONGODB_URL'];
        self::assertIsString($mongoUrl, 'MONGODB_URL must be set in environment for tests');
        $database = $_ENV['MONGODB_DATABASE'];
        self::assertIsString($database, 'MONGODB_DATABASE must be set in environment for tests');

        $client = new Client($mongoUrl);
        self::$mongoCollection = $client->selectCollection($database, 'users');
        self::$mongoCollection->drop();

        return new MongoUserReadModel(self::$mongoCollection);
    }

    private static function inMemorySeeder(UserReadModelInterface $readModel, string $email): void
    {
        self::assertInstanceOf(InMemoryUserReadModel::class, $readModel);
        $readModel->handleEvent(new UserRegistered(
            id: 'user-' . md5($email),
            email: $email,
            occurredAt: new \DateTimeImmutable(),
        ));
    }

    private static function mongoSeeder(UserReadModelInterface $readModel, string $email): void
    {
        self::assertNotNull(self::$mongoCollection);
        self::$mongoCollection->insertOne([
            '_id' => 'user-' . md5($email),
            'email' => strtolower($email),
        ]);
    }
}
