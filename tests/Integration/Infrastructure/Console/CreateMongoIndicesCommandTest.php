<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Console;

use App\Infrastructure\Console\CreateMongoIndicesCommand;
use App\Tests\Integration\Infrastructure\MongoHelper;
use MongoDB\Database;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\Driver\Exception\CommandException;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for CreateMongoIndicesCommand.
 *
 * Verifies that the command creates the expected indices and that
 * those indices actually enforce uniqueness constraints.
 *
 * @internal
 */
#[CoversClass(CreateMongoIndicesCommand::class)]
final class CreateMongoIndicesCommandTest extends KernelTestCase
{
    use MongoHelper;

    private Database $database;

    #[\Override]
    protected function setUp(): void
    {
        $this->database = self::getMongoDatabase();

        // Drop collections to ensure clean state
        $this->database->dropCollection('events');
        $this->database->dropCollection('users');
    }

    public function testItCreatesEventsCollectionIndex(): void
    {
        self::runCommand();

        $indices = $this->getIndexNames('events');

        self::assertContains('aggregate_version_unique', $indices);
    }

    public function testItCreatesUsersCollectionIndex(): void
    {
        self::runCommand();

        $indices = $this->getIndexNames('users');

        self::assertContains('email_unique', $indices);
    }

    public function testEventsIndexHasCorrectStructure(): void
    {
        self::runCommand();

        $index = $this->getIndexByName('events', 'aggregate_version_unique');

        self::assertNotNull($index, 'Index should exist');
        self::assertTrue($index['unique'], 'Index should be unique');
        self::assertSame(
            ['aggregate_id' => 1, 'aggregate_type' => 1, 'version' => 1],
            $index['key'],
        );
    }

    public function testUsersIndexHasCorrectStructure(): void
    {
        self::runCommand();

        $index = $this->getIndexByName('users', 'email_unique');

        self::assertNotNull($index, 'Index should exist');
        self::assertTrue($index['unique'], 'Index should be unique');
        self::assertSame(['email' => 1], $index['key']);
    }

    public function testEventsIndexEnforcesUniqueness(): void
    {
        self::runCommand();

        $collection = $this->database->selectCollection('events');

        // Insert first event
        $collection->insertOne([
            'aggregate_id' => 'user-123',
            'aggregate_type' => 'User',
            'version' => 1,
            'event_type' => 'UserRegistered',
            'event_data' => [],
        ]);

        // Attempt to insert duplicate (same aggregate_id, type, version)
        $this->expectException(BulkWriteException::class);
        $this->expectExceptionMessageMatches('/duplicate key error/');

        $collection->insertOne([
            'aggregate_id' => 'user-123',
            'aggregate_type' => 'User',
            'version' => 1,
            'event_type' => 'SomeOtherEvent',
            'event_data' => [],
        ]);
    }

    public function testEventsIndexAllowsDifferentVersionsForSameAggregate(): void
    {
        self::runCommand();

        $collection = $this->database->selectCollection('events');

        // Insert version 1
        $collection->insertOne([
            'aggregate_id' => 'user-123',
            'aggregate_type' => 'User',
            'version' => 1,
            'event_type' => 'UserRegistered',
        ]);

        // Insert version 2 - should succeed
        $collection->insertOne([
            'aggregate_id' => 'user-123',
            'aggregate_type' => 'User',
            'version' => 2,
            'event_type' => 'EmailChanged',
        ]);

        self::assertSame(2, $collection->countDocuments(['aggregate_id' => 'user-123']));
    }

    public function testEventsIndexAllowsSameVersionForDifferentAggregateTypes(): void
    {
        self::runCommand();

        $collection = $this->database->selectCollection('events');

        // Insert for User aggregate
        $collection->insertOne([
            'aggregate_id' => 'shared-id',
            'aggregate_type' => 'User',
            'version' => 1,
            'event_type' => 'UserRegistered',
        ]);

        // Insert for Order aggregate with same ID and version - should succeed
        $collection->insertOne([
            'aggregate_id' => 'shared-id',
            'aggregate_type' => 'Order',
            'version' => 1,
            'event_type' => 'OrderCreated',
        ]);

        self::assertSame(2, $collection->countDocuments(['aggregate_id' => 'shared-id']));
    }

    public function testUsersIndexEnforcesUniqueness(): void
    {
        self::runCommand();

        $collection = $this->database->selectCollection('users');

        // Insert first user
        $collection->insertOne([
            '_id' => 'user-123',
            'email' => 'test@example.com',
        ]);

        // Attempt to insert duplicate email
        $this->expectException(BulkWriteException::class);
        $this->expectExceptionMessageMatches('/duplicate key error/');

        $collection->insertOne([
            '_id' => 'user-456',
            'email' => 'test@example.com',
        ]);
    }

    public function testCommandFailsWhenDuplicateDataExists(): void
    {
        $collection = $this->database->selectCollection('users');

        // Insert duplicate emails before creating index
        $collection->insertOne(['_id' => 'user-1', 'email' => 'duplicate@example.com']);
        $collection->insertOne(['_id' => 'user-2', 'email' => 'duplicate@example.com']);

        $this->expectException(CommandException::class);

        self::runCommand();
    }

    public function testCommandCreatesCollectionsImplicitly(): void
    {
        // Collections were dropped in setUp, verify they don't exist
        $collectionNames = iterator_to_array($this->database->listCollectionNames());
        self::assertNotContains('events', $collectionNames);
        self::assertNotContains('users', $collectionNames);

        // Run command - should create collections implicitly
        self::runCommand();

        // Verify indices exist (which means collections were created)
        self::assertContains('aggregate_version_unique', $this->getIndexNames('events'));
        self::assertContains('email_unique', $this->getIndexNames('users'));
    }

    public function testCommandIsIdempotent(): void
    {
        // Run twice - should not throw
        self::runCommand();
        $exitCode = self::runCommand();

        self::assertSame(0, $exitCode);
    }

    public function testCommandShowsAlreadyExistsMessage(): void
    {
        // Run first time to create indices
        self::runCommand();

        // Run second time and check output
        $output = self::runCommandWithOutput();

        self::assertStringContainsString('aggregate_version_unique already exists', $output);
        self::assertStringContainsString('email_unique already exists', $output);
    }

    public function testCommandShowsCreatedMessage(): void
    {
        $output = self::runCommandWithOutput();

        self::assertStringContainsString('aggregate_version_unique created', $output);
        self::assertStringContainsString('email_unique created', $output);
        self::assertStringContainsString('Done', $output);
    }

    /**
     * @return list<string>
     */
    private function getIndexNames(string $collectionName): array
    {
        $indices = iterator_to_array($this->database->selectCollection($collectionName)->listIndexes());

        return array_values(array_map(static fn ($index) => $index->getName(), $indices));
    }

    /**
     * @return array{key: array<string, int>, unique: bool}|null
     */
    private function getIndexByName(string $collectionName, string $indexName): ?array
    {
        $indices = iterator_to_array($this->database->selectCollection($collectionName)->listIndexes());

        foreach ($indices as $index) {
            if ($index->getName() === $indexName) {
                /** @var array<string, int> $key */
                $key = $index->getKey();

                return [
                    'key' => $key,
                    'unique' => $index->isUnique(),
                ];
            }
        }

        return null;
    }

    private static function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new Application($kernel);
        $command = $application->find('app:create-indices');

        return new CommandTester($command);
    }

    private static function runCommand(): int
    {
        return self::createCommandTester()->execute([]);
    }

    private static function runCommandWithOutput(): string
    {
        $tester = self::createCommandTester();
        $tester->execute([]);

        return $tester->getDisplay();
    }
}
