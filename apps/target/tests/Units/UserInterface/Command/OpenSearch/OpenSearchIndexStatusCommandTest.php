<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use App\Tests\Utils\MockHelpersTrait;
use App\UserInterface\Command\OpenSearch\OpenSearchIndexStatusCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OpenSearchIndexStatusCommand::class)]
class OpenSearchIndexStatusCommandTest extends TestCase
{
    use MockHelpersTrait;
    private OpenSearchMigrationManager $migrationManager;
    private OpenSearchIndexStatusCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->migrationManager = $this->createStub(OpenSearchMigrationManager::class);
        $this->buildCommand();
    }

    private function buildCommand(): void
    {
        $this->command = new OpenSearchIndexStatusCommand($this->migrationManager);

        $application = new Application();
        $application->addCommand($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertEquals('opensearch:index:status', $this->command->getName());
        $this->assertEquals('Show status of OpenSearch index aliases', $this->command->getDescription());
    }

    public function testCommandArguments(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasArgument('alias'));
        $this->assertEquals('Index alias to check (optional)', $definition->getArgument('alias')->getDescription());
    }

    public function testShowSpecificAliasStatusWithIndices(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $alias = 'test_index';
        $currentVersion = 'Version20240101000000';
        $indices = ['test_index_version20240101000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getCurrentIndexVersion')
            ->with($alias)
            ->willReturn($currentVersion);

        $migrationManager
            ->expects($this->once())
            ->method('getIndicesForAlias')
            ->with($alias)
            ->willReturn($indices);

        // Act
        $this->commandTester->execute([
            'alias' => $alias,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Status for index alias: {$alias}", $output);
        $this->assertStringContainsString('Current Status', $output);
        $this->assertStringContainsString('Current Version', $output);
        $this->assertStringContainsString($currentVersion, $output);
        $this->assertStringContainsString('Indices', $output);
        $this->assertStringContainsString('test_index_version20240101000000', $output);
        $this->assertStringContainsString('Index Count', $output);
        $this->assertStringContainsString('1', $output);
    }

    public function testShowSpecificAliasStatusWithNoIndices(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $alias = 'nonexistent_index';
        $indices = [];

        $migrationManager
            ->expects($this->once())
            ->method('getCurrentIndexVersion')
            ->with($alias)
            ->willReturn(null);

        $migrationManager
            ->expects($this->once())
            ->method('getIndicesForAlias')
            ->with($alias)
            ->willReturn($indices);

        // Act
        $this->commandTester->execute([
            'alias' => $alias,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Status for index alias: {$alias}", $output);
        $this->assertStringContainsString("No indices found for alias: {$alias}", $output);
    }

    public function testShowSpecificAliasStatusWithMultipleIndices(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $alias = 'multi_index';
        $currentVersion = 'Version20240102000000';
        $indices = ['multi_index_version20240101000000', 'multi_index_version20240102000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getCurrentIndexVersion')
            ->with($alias)
            ->willReturn($currentVersion);

        $migrationManager
            ->expects($this->once())
            ->method('getIndicesForAlias')
            ->with($alias)
            ->willReturn($indices);

        // Act
        $this->commandTester->execute([
            'alias' => $alias,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Status for index alias: {$alias}", $output);
        $this->assertStringContainsString('Current Status', $output);
        $this->assertStringContainsString($currentVersion, $output);
        $this->assertStringContainsString(
            'multi_index_version20240101000000, multi_index_version20240102000000',
            $output
        );
        $this->assertStringContainsString('2', $output);
    }

    public function testShowSpecificAliasStatusWithError(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $alias = 'error_index';
        $errorMessage = 'Connection failed';

        $migrationManager
            ->expects($this->once())
            ->method('getCurrentIndexVersion')
            ->with($alias)
            ->willThrowException(new \Exception($errorMessage));

        // Act
        $this->commandTester->execute([
            'alias' => $alias,
        ]);

        // Assert
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Status for index alias: {$alias}", $output);
        $this->assertStringContainsString("Error getting status for alias {$alias}: {$errorMessage}", $output);
    }

    public function testShowAllAliasesStatusWithNoMigrations(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn([]);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn([]);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Index Aliases Status', $output);
        $this->assertStringContainsString('No migrations found.', $output);
    }

    public function testShowAllAliasesStatusWithMigrations(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'StatusTestVersion20240101000000' => 'OpenSearchMigrations\\StatusTestVersion20240101000000',
            'StatusTestVersion20240102000000' => 'OpenSearchMigrations\\StatusTestVersion20240102000000',
        ];

        $executedMigrations = ['StatusTestVersion20240101000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Index Aliases Status', $output);
        $this->assertStringContainsString('Available Migrations', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('Version', $output);
        $this->assertStringContainsString('Description', $output);
    }

    public function testShowAllAliasesStatusWithMigrationInstantiationError(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'StatusTestVersion20240101000000' => 'OpenSearchMigrations\\StatusTestVersion20240101000000',
        ];

        $executedMigrations = [];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Index Aliases Status', $output);
        $this->assertStringContainsString('Available Migrations', $output);
        $this->assertStringContainsString('Test migration 1', $output);
    }

    public function testShowAllAliasesStatusWithError(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $errorMessage = 'Failed to get migrations';

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willThrowException(new \Exception($errorMessage));

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Index Aliases Status', $output);
        $this->assertStringContainsString("Error getting aliases status: {$errorMessage}", $output);
    }

    public function testShowSpecificAliasStatusWithNullVersion(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $alias = 'no_version_index';
        $indices = ['no_version_index_old'];

        $migrationManager
            ->expects($this->once())
            ->method('getCurrentIndexVersion')
            ->with($alias)
            ->willReturn(null);

        $migrationManager
            ->expects($this->once())
            ->method('getIndicesForAlias')
            ->with($alias)
            ->willReturn($indices);

        // Act
        $this->commandTester->execute([
            'alias' => $alias,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString("Status for index alias: {$alias}", $output);
        $this->assertStringContainsString('Current Status', $output);
        $this->assertStringContainsString('Current Version', $output);
        $this->assertStringContainsString('None', $output);
        $this->assertStringContainsString('no_version_index_old', $output);
    }

    public function testShowAllAliasesStatusWithMixedMigrationStatus(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'StatusTestVersion20240101000000' => 'OpenSearchMigrations\\StatusTestVersion20240101000000',
            'StatusTestVersion20240102000000' => 'OpenSearchMigrations\\StatusTestVersion20240102000000',
            'StatusTestVersion20240103000000' => 'OpenSearchMigrations\\StatusTestVersion20240103000000',
        ];

        $executedMigrations = ['StatusTestVersion20240101000000', 'StatusTestVersion20240103000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Index Aliases Status', $output);
        $this->assertStringContainsString('Available Migrations', $output);
        $this->assertStringContainsString('Status', $output);
        $this->assertStringContainsString('Version', $output);
        $this->assertStringContainsString('Description', $output);
    }
}

// Mock migration class for testing

namespace OpenSearchMigrations;

use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

class StatusTestVersion20240101000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test migration 1';
    }

    public function up(): void
    {
        // Mock implementation
    }

    public function down(): void
    {
        // Mock implementation
    }
}

class StatusTestVersion20240102000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test migration 2';
    }

    public function up(): void
    {
        // Mock implementation
    }

    public function down(): void
    {
        // Mock implementation
    }
}

class StatusTestVersion20240103000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test migration 3';
    }

    public function up(): void
    {
        // Mock implementation
    }

    public function down(): void
    {
        // Mock implementation
    }
}
