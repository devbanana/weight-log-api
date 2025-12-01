<?php

declare(strict_types=1);

namespace App\Infrastructure\Console;

use MongoDB\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:create-indices',
    description: 'Create MongoDB indices for optimal performance and data integrity',
)]
final class CreateMongoIndicesCommand extends Command
{
    public function __construct(
        private readonly Collection $eventsCollection,
        private readonly Collection $usersCollection,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Creating MongoDB Indices');

        // Events collection: unique compound index for optimistic concurrency
        $io->section('Events Collection');
        self::createIndexIfNotExists(
            $io,
            $this->eventsCollection,
            ['aggregate_id' => 1, 'aggregate_type' => 1, 'version' => 1],
            ['unique' => true, 'name' => 'aggregate_version_unique'],
        );

        // Users collection: unique index on email for read model integrity
        $io->section('Users Collection');
        self::createIndexIfNotExists(
            $io,
            $this->usersCollection,
            ['email' => 1],
            ['unique' => true, 'name' => 'email_unique'],
        );

        $io->success('Done.');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, int>                $keys
     * @param array{name: string, unique: bool} $options
     */
    private static function createIndexIfNotExists(
        SymfonyStyle $io,
        Collection $collection,
        array $keys,
        array $options,
    ): void {
        $indexName = $options['name'];
        $existingIndices = iterator_to_array($collection->listIndexes());
        $existingNames = array_map(static fn ($index) => $index->getName(), $existingIndices);

        if (in_array($indexName, $existingNames, true)) {
            $io->writeln(sprintf('  <comment>%s</comment> already exists', $indexName));

            return;
        }

        $collection->createIndex($keys, $options);
        $io->writeln(sprintf('  <info>%s</info> created', $indexName));
    }
}
