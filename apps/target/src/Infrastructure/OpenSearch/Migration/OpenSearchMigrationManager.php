<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Migration;

use OpenSearch\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

class OpenSearchMigrationManager
{
    public const string MIGRATION_NAMESPACE = 'OpenSearchMigrations';
    public const string MIGRATION_INDEX = 'opensearch_migrations';

    public function __construct(
        private readonly Client $openSearchClient,
        private readonly OpenSearchMigrationFinder $migrationFinder,
        #[Autowire('%env(int:OPENSEARCH_NUM_SHARDS)%')]
        private readonly int $defaultNumberOfShards = 1,
        #[Autowire('%env(int:OPENSEARCH_NUM_REPLICAS)%')]
        private readonly int $defaultNumberOfReplicas = 0,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Get executed migrations from OpenSearch.
     *
     * @return array<string>
     */
    public function getExecutedMigrations(): array
    {
        try {
            if (!$this->indexExists(self::MIGRATION_INDEX)) {
                return [];
            }

            $response = $this->openSearchClient->search([
                'index' => self::MIGRATION_INDEX,
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                    'sort' => [[
                        'version' => [
                            'order' => 'asc',
                        ],
                    ]],
                    'size' => 1000,
                ],
            ]);

            $migrations = [];
            /** @var array<string, mixed> $responseArray */
            $responseArray = $response;
            if (\is_array($responseArray['hits']) && isset($responseArray['hits']['hits']) && is_iterable(
                $responseArray['hits']['hits']
            )) {
                foreach ($responseArray['hits']['hits'] as $hit) {
                    $migrations[] = $hit['_source']['version'];
                }
            }

            return $migrations;
        } catch (\Exception $e) {
            $this->logger?->error('Error getting executed migrations', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get current version of an index alias.
     */
    public function getCurrentIndexVersion(string $indexAlias): ?string
    {
        try {
            $response = $this->openSearchClient->indices()
                ->getAlias([
                    'name' => $indexAlias,
                ]);
            /** @var array<string, mixed> $responseArray */
            $responseArray = $response;
            if (empty($responseArray)) {
                return null;
            }

            $indices = array_keys($responseArray);

            // Extract version from index name (format: alias_version)
            $indexName = $indices[0];
            if (str_contains($indexName, '_')) {
                $parts = explode('_', $indexName);

                return end($parts);
            }

            return null;
        } catch (\Exception $e) {
            // Don't log error if alias doesn't exist (404) - this is expected for new indices
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                $this->logger?->debug('Alias does not exist yet', [
                    'alias' => $indexAlias,
                ]);

                return null;
            }

            $this->logger?->error('Error getting current index version', [
                'alias' => $indexAlias,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get all indices for an alias.
     *
     * @return array<string>
     */
    public function getIndicesForAlias(string $indexAlias): array
    {
        try {
            $response = $this->openSearchClient->indices()
                ->getAlias([
                    'name' => $indexAlias,
                ]);
            /** @var array<string, mixed> $responseArray */
            $responseArray = $response;

            return array_keys($responseArray);
        } catch (\Exception $e) {
            // Don't log error if alias doesn't exist (404) - this is expected for new indices
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                $this->logger?->debug('Alias does not exist yet', [
                    'alias' => $indexAlias,
                ]);

                return [];
            }

            $this->logger?->error('Error getting indices for alias', [
                'alias' => $indexAlias,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get all indices that start with a specific prefix.
     *
     * @return array<string>
     */
    private function getIndicesWithPrefix(string $prefix): array
    {
        try {
            $response = $this->openSearchClient->indices()
                ->get([
                    'index' => $prefix . '*',
                ]);
            /** @var array<string, mixed> $responseArray */
            $responseArray = $response;

            return array_keys($responseArray);
        } catch (\Exception $e) {
            // Don't log error if no indices match the pattern (404) - this is expected
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'not found')) {
                $this->logger?->debug('No indices found with prefix', [
                    'prefix' => $prefix,
                ]);

                return [];
            }

            $this->logger?->error('Error getting indices with prefix', [
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Copy data from one index to another.
     */
    private function copyIndexData(string $sourceIndex, string $destIndex): void
    {
        try {
            $this->openSearchClient->reindex([
                'body' => [
                    'source' => [
                        'index' => $sourceIndex,
                    ],
                    'dest' => [
                        'index' => $destIndex,
                    ],
                ],
                'wait_for_completion' => true,
            ]);

            $this->logger?->debug('Copied index data', [
                'source' => $sourceIndex,
                'dest' => $destIndex,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Error copying index data', [
                'source' => $sourceIndex,
                'dest' => $destIndex,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Switch alias to point to a new index.
     */
    private function switchAlias(string $indexAlias, string $newIndexName): void
    {
        try {
            // A plain index (not an alias) with the same name blocks alias creation.
            // This happens when migrating from a non-versioned setup. Manual intervention required:
            //   1. Reindex data: POST _reindex { "source": { "index": "<alias>" }, "dest": { "index": "<alias>_<version>" } }
            //   2. Delete the plain index: DELETE /<alias>
            //   3. Re-run migrations
            if ($this->indexExists($indexAlias) && !$this->isAlias($indexAlias)) {
                throw new \RuntimeException(
                    "Cannot create alias '{$indexAlias}': a plain index with the same name already exists. " . 'You must manually reindex the data and delete the plain index before re-running migrations.'
                );
            }

            $currentIndices = $this->getIndicesForAlias($indexAlias);

            $actions = [];

            // Remove alias from current indices
            foreach ($currentIndices as $currentIndex) {
                $actions[] = [
                    'remove' => [
                        'index' => $currentIndex,
                        'alias' => $indexAlias,
                    ],
                ];
            }

            // Add alias to new index
            $actions[] = [
                'add' => [
                    'index' => $newIndexName,
                    'alias' => $indexAlias,
                ],
            ];

            $this->openSearchClient->indices()
                ->updateAliases([
                    'body' => [
                        'actions' => $actions,
                    ],
                ]);

            $this->logger?->debug('Switched alias', [
                'alias' => $indexAlias,
                'new_index' => $newIndexName,
                'old_indices' => $currentIndices,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Error switching alias', [
                'alias' => $indexAlias,
                'new_index' => $newIndexName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Delete old indices that are no longer needed.
     */
    private function deleteOldIndices(string $indexAlias, string $keepVersion): void
    {
        try {
            // Get all indices that start with the alias name followed by underscore
            $allIndices = $this->getIndicesWithPrefix($indexAlias . '_');
            $this->logger?->debug('Deleting old index data', [
                'all_indices' => $allIndices,
            ]);
            $keepVersion = strtolower($keepVersion);

            foreach ($allIndices as $indexName) {
                // Extract version from index name
                if (str_contains($indexName, '_')) {
                    $parts = explode('_', $indexName);
                    $version = end($parts);

                    if ($version !== $keepVersion) {
                        $this->openSearchClient->indices()
                            ->delete([
                                'index' => $indexName,
                            ]);
                        $this->logger?->debug('Deleted old index', [
                            'index' => $indexName,
                            'version' => $version,
                            'keepVersion' => $keepVersion,
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger?->error('Error deleting old indices', [
                'alias' => $indexAlias,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Execute a migration with automatic versioning.
     */
    public function executeMigration(string $version, AbstractOpenSearchMigration $migration): void
    {
        $this->logger?->debug('Starting migration execution', [
            'version' => $version,
            'description' => $migration->getDescription(),
        ]);

        try {
            // Execute the migration to collect operations
            $migration->up();

            // Get the operations and execute them transactionally
            $operations = $migration->getOperations();
            $this->executeOperations($version, $operations);

            // Clear operations after successful execution
            $migration->clearOperations();

            $this->logger?->debug('Migration executed successfully', [
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Migration failed', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Execute operations transactionally.
     *
     * @param array<int, array<string, mixed>> $operations
     */
    private function executeOperations(string $version, array $operations): void
    {
        foreach ($operations as $operation) {
            /** @var array{type: string, name: string, config?: array<string, mixed>} $operation */
            $operationType = $operation['type'];
            $operationName = $operation['name'];
            $operationConfig = $operation['config'] ?? [];

            switch ($operationType) {
                case 'create_index':
                    $this->executeCreateIndex($version, (string) $operationName, (array) $operationConfig);
                    break;
                case 'update_index':
                    $this->executeUpdateIndex($version, (string) $operationName, (array) $operationConfig);
                    break;
                case 'delete_index':
                    $this->executeDeleteIndex($version, (string) $operationName);
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown operation type: {$operationType}");
            }
        }
    }

    /**
     * Execute create index operation with versioning.
     *
     * @param array<string, mixed> $config
     */
    private function executeCreateIndex(string $version, string $name, array $config): void
    {
        $versionedIndexName = $this->getVersionedIndexName($name, $version);

        try {
            // Check if index already exists
            if ($this->indexExists($versionedIndexName)) {
                $this->logger?->warning('Index already exists, skipping creation', [
                    'name' => $name,
                    'version' => $version,
                    'index' => $versionedIndexName,
                ]);

                return;
            }

            // Create the versioned index
            $this->openSearchClient->indices()
                ->create([
                    'index' => $versionedIndexName,
                    'body' => $this->applyDefaultConfig($config),
                ]);

            $this->logger?->debug('Created versioned index', [
                'name' => $name,
                'version' => $version,
                'index' => $versionedIndexName,
            ]);

            // Get current version to copy data if needed
            $currentVersion = $this->getCurrentIndexVersion($name);
            if ($currentVersion) {
                $currentIndexName = $this->getVersionedIndexName($name, $currentVersion);
                if ($this->indexExists($currentIndexName)) {
                    try {
                        // Copy data from current version to new version
                        $this->copyIndexData($currentIndexName, $versionedIndexName);
                        $this->logger?->debug('Copied data from previous version', [
                            'from' => $currentIndexName,
                            'to' => $versionedIndexName,
                        ]);
                    } catch (\Exception $e) {
                        $this->logger?->error('Failed to copy data from previous version', [
                            'from' => $currentIndexName,
                            'to' => $versionedIndexName,
                            'error' => $e->getMessage(),
                        ]);
                        // Clean up the created index since data copy failed
                        $this->openSearchClient->indices()
                            ->delete([
                                'index' => $versionedIndexName,
                            ]);
                        throw new \RuntimeException(
                            "Failed to copy data from previous version: {$e->getMessage()}",
                            0,
                            $e
                        );
                    }
                }
            }

            // Switch alias to new index
            try {
                $this->switchAlias($name, $versionedIndexName);
                $this->logger?->debug('Switched alias to new index', [
                    'alias' => $name,
                    'index' => $versionedIndexName,
                ]);
            } catch (\Exception $e) {
                $this->logger?->error('Failed to switch alias to new index', [
                    'alias' => $name,
                    'index' => $versionedIndexName,
                    'error' => $e->getMessage(),
                ]);
                // Clean up the created index since alias switch failed
                $this->openSearchClient->indices()
                    ->delete([
                        'index' => $versionedIndexName,
                    ]);
                throw new \RuntimeException("Failed to switch alias to new index: {$e->getMessage()}", 0, $e);
            }

            // Delete old indices (keep the new one)
            try {
                $this->deleteOldIndices($name, $version);
                $this->logger?->debug('Cleaned up old indices', [
                    'name' => $name,
                    'version' => $version,
                ]);
            } catch (\Exception $e) {
                $this->logger?->warning('Failed to clean up old indices', [
                    'name' => $name,
                    'version' => $version,
                    'error' => $e->getMessage(),
                ]);
                // Don't throw here - the main operation succeeded, cleanup failure is not critical
            }

            $this->logger?->debug('Successfully created index with versioning', [
                'name' => $name,
                'version' => $version,
                'index' => $versionedIndexName,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Failed to create index with versioning', [
                'name' => $name,
                'version' => $version,
                'index' => $versionedIndexName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Execute update index operation with versioning.
     *
     * @param array<string, mixed> $config
     */
    private function executeUpdateIndex(string $version, string $name, array $config): void
    {
        $versionedIndexName = $this->getVersionedIndexName($name, $version);

        // Get current version
        $currentVersion = $this->getCurrentIndexVersion($name);
        if (!$currentVersion) {
            throw new \RuntimeException("Cannot update index '{$name}' - no current version found");
        }

        $currentIndexName = $this->getVersionedIndexName($name, $currentVersion);

        $this->openSearchClient->indices()
            ->create([
                'index' => $versionedIndexName,
                'body' => $this->applyDefaultConfig($config),
            ]);

        // Copy data from current version to new version
        $this->copyIndexData($currentIndexName, $versionedIndexName);

        // Switch alias to new index
        $this->switchAlias($name, $versionedIndexName);

        // Delete old indices (keep the new one)
        $this->deleteOldIndices($name, $version);

        $this->logger?->debug('Updated index with versioning', [
            'name' => $name,
            'version' => $version,
            'index' => $versionedIndexName,
        ]);
    }

    /**
     * Execute delete index operation.
     */
    private function executeDeleteIndex(string $version, string $name): void
    {
        // Get current version
        $currentVersion = $this->getCurrentIndexVersion($name);
        if ($currentVersion) {
            $currentIndexName = $this->getVersionedIndexName($name, $currentVersion);

            // Remove alias
            $this->openSearchClient->indices()
                ->deleteAlias([
                    'index' => $currentIndexName,
                    'name' => $name,
                ]);

            // Delete the index
            $this->openSearchClient->indices()
                ->delete([
                    'index' => $currentIndexName,
                ]);
        }

        $this->logger?->debug('Deleted index', [
            'name' => $name,
            'version' => $version,
        ]);
    }

    /**
     * Rollback a migration.
     */
    public function rollbackMigration(string $version, AbstractOpenSearchMigration $migration): void
    {
        $this->logger?->debug('Starting migration rollback', [
            'version' => $version,
            'description' => $migration->getDescription(),
        ]);

        try {
            // Execute the rollback to collect operations
            $migration->down();

            // Get the rollback operations and execute them
            $operations = $migration->getOperations();
            $this->executeRollbackOperations($version, $operations);

            // Clear operations after successful rollback
            $migration->clearOperations();

            $this->logger?->debug('Migration rollback completed successfully', [
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Migration rollback failed', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all available migrations.
     *
     * @return array<string, class-string<AbstractOpenSearchMigration>>
     */
    public function getAvailableMigrations(): array
    {
        return $this->migrationFinder->getAvailableMigrations();
    }

    /**
     * Execute rollback operations.
     *
     * @param array<int, array<string, mixed>> $operations
     */
    private function executeRollbackOperations(string $version, array $operations): void
    {
        foreach ($operations as $operation) {
            /** @var array{type: string, name: string, config?: array<string, mixed>} $operation */
            $operationType = $operation['type'];
            $operationName = $operation['name'];
            $operationConfig = $operation['config'] ?? [];

            switch ($operationType) {
                case 'create_index':
                    // For rollback, delete the index
                    $this->executeDeleteIndex($version, (string) $operationName);
                    break;
                case 'update_index':
                    // For rollback, we need to revert to previous version
                    $this->revertToPreviousVersion((string) $operationName, $version);
                    break;
                case 'delete_index':
                    // For rollback, recreate the index
                    $this->executeCreateIndex($version, (string) $operationName, (array) $operationConfig);
                    break;
                default:
                    throw new \InvalidArgumentException("Unknown operation type: {$operationType}");
            }
        }
    }

    private function revertToPreviousVersion(string $name, string $currentVersion): void
    {
        $executedMigrations = $this->getExecutedMigrations();
        $currentIndex = array_search($currentVersion, $executedMigrations);

        if (false !== $currentIndex && \is_int($currentIndex) && $currentIndex > 0) {
            $previousVersion = $executedMigrations[$currentIndex - 1];
            $previousIndexName = $this->getVersionedIndexName($name, $previousVersion);

            if ($this->indexExists($previousIndexName)) {
                $this->switchAlias($name, $previousIndexName);
                $this->deleteOldIndices($name, $previousVersion);
            }
        }
    }

    /**
     * Mark a migration as executed.
     */
    public function markMigrationAsExecuted(string $version, string $description): void
    {
        try {
            if (!$this->indexExists(self::MIGRATION_INDEX)) {
                $this->createMigrationsIndex();
            }

            $this->openSearchClient->index([
                'index' => self::MIGRATION_INDEX,
                'id' => $version,
                'body' => [
                    'version' => $version,
                    'description' => $description,
                    'executed_at' => new \DateTime()
                        ->format('c'), // ISO 8601 format
                ],
            ]);

            $this->logger?->debug('Migration marked as executed', [
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Error marking migration as executed', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mark a migration as not executed (rollback).
     */
    public function markMigrationAsNotExecuted(string $version): void
    {
        try {
            if (!$this->indexExists(self::MIGRATION_INDEX)) {
                return;
            }

            $this->openSearchClient->delete([
                'index' => self::MIGRATION_INDEX,
                'id' => $version,
            ]);

            $this->logger?->debug('Migration marked as not executed', [
                'version' => $version,
            ]);
        } catch (\Exception $e) {
            $this->logger?->error('Error marking migration as not executed', [
                'version' => $version,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Execute pending migrations.
     *
     * @return array<string>
     */
    public function executePendingMigrations(): array
    {
        $availableMigrations = $this->getAvailableMigrations();
        $executedMigrations = $this->getExecutedMigrations();

        $pendingMigrations = array_diff(array_keys($availableMigrations), $executedMigrations);
        $executed = [];

        foreach ($pendingMigrations as $version) {
            $migrationClass = $availableMigrations[$version];
            $migration = new $migrationClass();

            $this->logger?->debug('Executing OpenSearch migration', [
                'version' => $version,
                'description' => $migration->getDescription(),
            ]);

            try {
                $this->executeMigration($version, $migration);
                $this->markMigrationAsExecuted($version, $migration->getDescription());
                $executed[] = $version;
            } catch (\Exception $e) {
                $this->logger?->error('Error executing migration', [
                    'version' => $version,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $executed;
    }

    /**
     * Rollback the last migration.
     */
    public function rollbackLastMigration(): ?string
    {
        $executedMigrations = $this->getExecutedMigrations();

        if (empty($executedMigrations)) {
            return null;
        }

        $lastMigration = end($executedMigrations);
        $availableMigrations = $this->getAvailableMigrations();

        if (!isset($availableMigrations[$lastMigration])) {
            throw new \RuntimeException("Migration class for version {$lastMigration} not found");
        }

        $migrationClass = $availableMigrations[$lastMigration];
        $migration = new $migrationClass();

        $this->logger?->debug('Rolling back OpenSearch migration', [
            'version' => $lastMigration,
            'description' => $migration->getDescription(),
        ]);

        try {
            $this->rollbackMigration($lastMigration, $migration);
            $this->markMigrationAsNotExecuted($lastMigration);

            return $lastMigration;
        } catch (\Exception $e) {
            $this->logger?->error('Error rolling back migration', [
                'version' => $lastMigration,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function getVersionedIndexName(string $indexAlias, string $version): string
    {
        return $indexAlias . '_' . strtolower($version);
    }

    private function indexExists(string $indexName): bool
    {
        try {
            return $this->openSearchClient->indices()
                ->exists([
                    'index' => $indexName,
                ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function isAlias(string $name): bool
    {
        try {
            return $this->openSearchClient->indices()
                ->existsAlias([
                    'name' => $name,
                ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function createMigrationsIndex(): void
    {
        $this->openSearchClient->indices()
            ->create([
                'index' => self::MIGRATION_INDEX,
                'body' => [
                    'settings' => [
                        // Migrations index should be small, 1 shard is sufficient
                        'number_of_shards' => 1,
                        // Use default replicas, to ensure availability
                        'number_of_replicas' => $this->defaultNumberOfReplicas,
                    ],
                    'mappings' => [
                        'properties' => [
                            'version' => [
                                'type' => 'keyword',
                            ],
                            'description' => [
                                'type' => 'text',
                            ],
                            'executed_at' => [
                                'type' => 'date',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function applyDefaultConfig(array $config): array
    {
        if (!isset($config['settings'])) {
            $config['settings'] = [];
        }

        Assert::isArray($config['settings']);

        if (!isset($config['settings']['number_of_shards'])) {
            $config['settings']['number_of_shards'] = $this->defaultNumberOfShards;
        }

        if (!isset($config['settings']['number_of_replicas'])) {
            $config['settings']['number_of_replicas'] = $this->defaultNumberOfReplicas;
        }

        return $config;
    }
}
