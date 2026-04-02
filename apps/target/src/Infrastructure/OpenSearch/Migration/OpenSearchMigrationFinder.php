<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Migration;

use Symfony\Component\Finder\Finder;

readonly class OpenSearchMigrationFinder
{
    public function __construct(
        private string $openSearchMigrationDirectory,
        private string $migrationNamespace = OpenSearchMigrationManager::MIGRATION_NAMESPACE,
    ) {
    }

    public function getMigrationDirectory(): string
    {
        return $this->openSearchMigrationDirectory;
    }

    /**
     * Get all available migrations.
     *
     * @return array<string, class-string<AbstractOpenSearchMigration>>
     */
    public function getAvailableMigrations(): array
    {
        $migrations = [];
        $migrationPath = $this->openSearchMigrationDirectory;

        if (!is_dir($migrationPath)) {
            return $migrations;
        }

        $finder = new Finder();
        $finder->files()
            ->in($migrationPath)
            ->name('Version*.php')
            ->sortByName();

        foreach ($finder as $file) {
            $className = $file->getBasename('.php');
            $fullClassName = $this->migrationNamespace . '\\' . $className;
            include_once $file->getRealPath();
            if (class_exists($fullClassName) && is_subclass_of($fullClassName, AbstractOpenSearchMigration::class)) {
                $migrations[$className] = $fullClassName;
            }
        }

        return $migrations;
    }
}
