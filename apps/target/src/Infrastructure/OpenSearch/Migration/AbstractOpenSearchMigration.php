<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Migration;

abstract class AbstractOpenSearchMigration
{
    /**
     * @var array<int, array{type: string, name: string, config?: array<string, mixed>}> Operations to be executed
     */
    protected array $operations = [];

    /**
     * Get the migration description.
     */
    abstract public function getDescription(): string;

    /**
     * Execute the migration - collect operations in memory.
     */
    abstract public function up(): void;

    /**
     * Rollback the migration - collect rollback operations in memory.
     */
    abstract public function down(): void;

    /**
     * Get the operations collected during migration execution.
     *
     * @return array<int, array{type: string, name: string, config?: array<string, mixed>}>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Clear operations (used by manager).
     */
    public function clearOperations(): void
    {
        $this->operations = [];
    }

    /**
     * Create a new index with the given configuration
     * The manager will handle versioning automatically.
     *
     * @param array<string, mixed> $config
     */
    protected function createIndex(string $name, array $config): void
    {
        $this->operations[] = [
            'type' => 'create_index',
            'name' => $name,
            'config' => $config,
        ];
    }

    /**
     * Update an existing index with new settings/mappings
     * The manager will handle versioning automatically.
     *
     * @param array<string, mixed> $config
     */
    protected function updateIndex(string $name, array $config): void
    {
        $this->operations[] = [
            'type' => 'update_index',
            'name' => $name,
            'config' => $config,
        ];
    }

    /**
     * Delete an index
     * The manager will handle versioning automatically.
     */
    protected function deleteIndex(string $name): void
    {
        $this->operations[] = [
            'type' => 'delete_index',
            'name' => $name,
        ];
    }
}
