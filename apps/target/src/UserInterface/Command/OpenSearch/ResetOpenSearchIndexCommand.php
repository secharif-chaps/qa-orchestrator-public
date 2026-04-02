<?php

declare(strict_types=1);

namespace App\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use OpenSearch\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'opensearch:index:reset',
    description: '[DEV ONLY] Delete and recreate an OpenSearch index with latest migrations'
)]
class ResetOpenSearchIndexCommand extends Command
{
    public function __construct(
        private readonly Client $openSearchClient,
        private readonly OpenSearchMigrationManager $migrationManager,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Security check: prevent execution in production
        if ('prod' === $this->environment) {
            $io->error('This command cannot be executed in production environment.');

            return Command::FAILURE;
        }

        $indexNames = $this->getAllIndexNames();
        if (empty($indexNames)) {
            $io->warning('No OpenSearch indices found to reset.');

            return Command::SUCCESS;
        }

        if ($input->isInteractive() && !$io->confirm(
            'This will delete and recreate ALL OpenSearch indices. Are you sure you want to continue?',
            false
        )) {
            $io->warning('Operation cancelled by user.');

            return Command::SUCCESS;
        }

        $io->title('Resetting all OpenSearch indices');
        $io->info('Found indices to reset: ' . implode(', ', $indexNames));

        foreach ($indexNames as $indexName) {
            $result = $this->resetIndex($indexName, $io);
            if (Command::FAILURE === $result) {
                return Command::FAILURE;
            }
        }

        // Clear migration history
        try {
            $this->clearMigrationHistory();
            $io->info('Migration history cleared');
        } catch (\Exception $e) {
            $io->warning("Failed to clear migration history: {$e->getMessage()}");
        }

        // Re-run migrations (will create new versioned index with alias)
        try {
            $executed = $this->migrationManager->executePendingMigrations();

            if (empty($executed)) {
                $io->info('No migrations to execute');
            } else {
                $io->success('Executed migrations: ' . implode(', ', $executed));
            }
        } catch (\Exception $e) {
            $io->error("Failed to execute migrations: {$e->getMessage()}");

            return Command::FAILURE;
        }

        $io->success('All indices have been successfully reset!');

        return Command::SUCCESS;
    }

    private function resetIndex(string $indexAlias, SymfonyStyle $io): int
    {
        $io->section("Resetting index: {$indexAlias}");

        $deleted = false;

        // Delete versioned indices behind the alias
        $currentIndices = $this->migrationManager->getIndicesForAlias($indexAlias);
        foreach ($currentIndices as $indexName) {
            try {
                $this->openSearchClient->indices()
->delete([
    'index' => $indexName,
]);
                $io->info("Deleted versioned index: {$indexName}");
                $deleted = true;
            } catch (\Exception $e) {
                $io->error("Failed to delete index {$indexName}: {$e->getMessage()}");

                return Command::FAILURE;
            }
        }

        // Also delete a direct index with the same name (not behind an alias)
        if ($this->directIndexExists($indexAlias)) {
            try {
                $this->openSearchClient->indices()
->delete([
    'index' => $indexAlias,
]);
                $io->info("Deleted direct index: {$indexAlias}");
                $deleted = true;
            } catch (\Exception $e) {
                $io->error("Failed to delete direct index {$indexAlias}: {$e->getMessage()}");

                return Command::FAILURE;
            }
        }

        if (!$deleted) {
            $io->info("No existing index found for '{$indexAlias}'");
        }

        return Command::SUCCESS;
    }

    /**
     * Check if an index exists as a direct index (not an alias).
     */
    private function directIndexExists(string $indexName): bool
    {
        try {
            return $this->openSearchClient->indices()
->exists([
    'index' => $indexName,
]);
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Clear all migration history to force re-execution.
     */
    private function clearMigrationHistory(): void
    {
        try {
            // Delete the entire opensearch_migrations index to force full re-execution
            $this->openSearchClient->indices()
->delete([
    'index' => OpenSearchMigrationManager::MIGRATION_INDEX,
]);
        } catch (\Exception $e) {
            // If index doesn't exist, that's fine - nothing to clear
            if (!str_contains($e->getMessage(), '404') && !str_contains($e->getMessage(), 'not found')) {
                throw new \RuntimeException("Failed to clear migration history: {$e->getMessage()}", 0, $e);
            }
        }
    }

    /**
     * Get all index names referenced by migrations.
     *
     * @return array<string>
     */
    private function getAllIndexNames(): array
    {
        $indexNames = [];

        $migrations = $this->migrationManager->getAvailableMigrations();
        foreach ($migrations as $migrationClass) {
            $migration = new $migrationClass();
            $migration->up();

            foreach ($migration->getOperations() as $operation) {
                $indexNames[] = $operation['name'];
            }

            $migration->clearOperations();
        }

        return array_values(array_unique($indexNames));
    }
}
