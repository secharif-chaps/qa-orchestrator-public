<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Migration;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationFinder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OpenSearchMigrationFinder::class)]
class OpenSearchMigrationFinderTest extends TestCase
{
    private string $testMigrationDirectory;
    private OpenSearchMigrationFinder $finder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testMigrationDirectory = __DIR__ . '/../../../../resources/migrations/opensearch';
        $this->finder = new OpenSearchMigrationFinder(
            $this->testMigrationDirectory,
            'App\Tests\resources\migrations\opensearch'
        );
    }

    public function testGetMigrationDirectoryReturnsCorrectDirectory(): void
    {
        $directory = $this->finder->getMigrationDirectory();

        $this->assertEquals($this->testMigrationDirectory, $directory);
    }

    public function testGetAvailableMigrationsReturnsEmptyArrayWhenDirectoryDoesNotExist(): void
    {
        $finder = new OpenSearchMigrationFinder('/non/existent/directory');
        $migrations = $finder->getAvailableMigrations();

        $this->assertEmpty($migrations);
    }

    public function testGetAvailableMigrationsFindsValidMigrationFiles(): void
    {
        $migrations = $this->finder->getAvailableMigrations();

        $this->assertCount(3, $migrations);
        $this->assertArrayHasKey('Version20240101000000', $migrations);
        $this->assertArrayHasKey('Version20240102000000', $migrations);
        $this->assertArrayHasKey('Version20240103000000', $migrations);
        $this->assertEquals(
            'App\Tests\resources\migrations\opensearch\\Version20240101000000',
            $migrations['Version20240101000000']
        );
        $this->assertEquals(
            'App\Tests\resources\migrations\opensearch\\Version20240102000000',
            $migrations['Version20240102000000']
        );
        $this->assertEquals(
            'App\Tests\resources\migrations\opensearch\\Version20240103000000',
            $migrations['Version20240103000000']
        );
    }

    public function testGetAvailableMigrationsFiltersOutInvalidFiles(): void
    {
        $migrations = $this->finder->getAvailableMigrations();

        // Should only find the 3 valid migration files, filtering out:
        // - InvalidFile.php (doesn't start with Version)
        // - Version20240104000000.php (doesn't extend AbstractOpenSearchMigration)
        // - Version20240105000000.txt (not a PHP file)
        $this->assertCount(3, $migrations);
        $this->assertArrayHasKey('Version20240101000000', $migrations);
        $this->assertArrayHasKey('Version20240102000000', $migrations);
        $this->assertArrayHasKey('Version20240103000000', $migrations);
        $this->assertArrayNotHasKey('InvalidFile', $migrations);
        $this->assertArrayNotHasKey('Version20240104000000', $migrations);
        $this->assertArrayNotHasKey('Version20240105000000', $migrations);
    }

    public function testGetAvailableMigrationsReturnsMigrationsSortedByName(): void
    {
        $migrations = $this->finder->getAvailableMigrations();

        $this->assertCount(3, $migrations);
        $migrationKeys = array_keys($migrations);
        $this->assertEquals(
            ['Version20240101000000', 'Version20240102000000', 'Version20240103000000'],
            $migrationKeys
        );
    }

    public function testGetAvailableMigrationsHandlesFilesThatDoNotExtendAbstractOpenSearchMigration(): void
    {
        $migrations = $this->finder->getAvailableMigrations();

        // Should not include Version20240104000000 which doesn't extend AbstractOpenSearchMigration
        $this->assertArrayNotHasKey('Version20240104000000', $migrations);
    }
}
