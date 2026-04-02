<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use App\Tests\Utils\MockHelpersTrait;
use App\UserInterface\Command\OpenSearch\OpenSearchMigrationCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(OpenSearchMigrationCommand::class)]
class OpenSearchMigrationCommandTest extends TestCase
{
    use MockHelpersTrait;
    private OpenSearchMigrationManager $migrationManager;
    private OpenSearchMigrationCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->migrationManager = $this->createStub(OpenSearchMigrationManager::class);
        $this->buildCommand();
    }

    private function buildCommand(): void
    {
        $this->command = new OpenSearchMigrationCommand($this->migrationManager);

        $application = new Application();
        $application->addCommand($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertEquals('opensearch:migrations:migrate', $this->command->getName());
        $this->assertEquals('Execute pending OpenSearch migrations', $this->command->getDescription());
    }

    public function testCommandOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('dry-run'));
        $this->assertTrue($definition->hasOption('rollback'));
        $this->assertTrue($definition->hasOption('status'));
        $this->assertTrue($definition->hasOption('force'));

        $this->assertFalse($definition->getOption('dry-run')->acceptValue());
        $this->assertFalse($definition->getOption('rollback')->acceptValue());
        $this->assertFalse($definition->getOption('status')->acceptValue());
        $this->assertFalse($definition->getOption('force')->acceptValue());
    }

    public function testStatusOptionWithNoMigrations(): void
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
        $this->commandTester->execute([
            '--status' => true,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Migration Status', $output);
        $this->assertStringContainsString('No migrations have been executed.', $output);
        $this->assertStringContainsString('No pending migrations.', $output);
    }

    public function testStatusOptionWithMigrations(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
            'Version20240102000000' => 'OpenSearchMigrations\\Version20240102000000',
            'Version20240103000000' => 'OpenSearchMigrations\\Version20240103000000',
        ];

        $executedMigrations = ['Version20240101000000', 'Version20240102000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        // Act
        $this->commandTester->execute([
            '--status' => true,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Migration Status', $output);
        $this->assertStringContainsString('✓ Version20240101000000', $output);
        $this->assertStringContainsString('✓ Version20240102000000', $output);
        $this->assertStringContainsString('○ Version20240103000000', $output);
    }

    public function testRollbackOptionWithNoMigrations(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $migrationManager
            ->expects($this->once())
            ->method('rollbackLastMigration')
            ->willReturn(null);

        // Act
        $this->commandTester->execute([
            '--rollback' => true,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Rolling Back Last OpenSearch Migration', $output);
        $this->assertStringContainsString('No migrations to rollback.', $output);
    }

    public function testRollbackOptionWithSuccessfulRollback(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $migrationManager
            ->expects($this->once())
            ->method('rollbackLastMigration')
            ->willReturn('Version20240101000000');

        // Act
        $this->commandTester->execute([
            '--rollback' => true,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Rolling Back Last OpenSearch Migration', $output);
        $this->assertStringContainsString('Successfully rolled back migration: Version20240101000000', $output);
    }

    public function testRollbackOptionWithError(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $migrationManager
            ->expects($this->once())
            ->method('rollbackLastMigration')
            ->willThrowException(new \Exception('Rollback failed'));

        // Act
        $this->commandTester->execute([
            '--rollback' => true,
        ]);

        // Assert
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Rolling Back Last OpenSearch Migration', $output);
        $this->assertStringContainsString('Error rolling back migration: Rollback failed', $output);
    }

    public function testMigrateWithNoPendingMigrations(): void
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
        $this->assertStringContainsString('Executing OpenSearch Migrations', $output);
        $this->assertStringContainsString('No pending migrations.', $output);
    }

    public function testMigrateWithDryRun(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
            'Version20240102000000' => 'OpenSearchMigrations\\Version20240102000000',
        ];

        $executedMigrations = ['Version20240101000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        // Act
        $this->commandTester->execute([
            '--dry-run' => true,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Migration Plan (Dry Run)', $output);
        $this->assertStringContainsString('○ Version20240102000000', $output);
        $this->assertStringContainsString('This was a dry run. No migrations were executed.', $output);
    }

    public function testMigrateWithForceOption(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
        ];

        $executedMigrations = [];
        $executedVersions = ['Version20240101000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('executePendingMigrations')
            ->willReturn($executedVersions);

        // Act
        $this->commandTester->execute([
            '--force' => true,
        ]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Executing OpenSearch Migrations', $output);
        $this->assertStringContainsString('○ Version20240101000000', $output);
        $this->assertStringContainsString('Force mode enabled - executing migrations without confirmation.', $output);
        $this->assertStringContainsString('Successfully executed migrations: Version20240101000000', $output);
    }

    public function testMigrateWithSuccessfulExecution(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
        ];

        $executedMigrations = [];
        $executedVersions = ['Version20240101000000'];

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('executePendingMigrations')
            ->willReturn($executedVersions);

        // Act - Simulate user confirmation by providing 'yes' input
        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Executing OpenSearch Migrations', $output);
        $this->assertStringContainsString('○ Version20240101000000', $output);
        $this->assertStringContainsString('Successfully executed migrations: Version20240101000000', $output);
    }

    public function testMigrateWithUserCancellation(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
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

        // Should not call executePendingMigrations when user cancels
        $migrationManager
            ->expects($this->never())
            ->method('executePendingMigrations');

        // Act - Simulate user cancellation by providing 'no' input
        $this->commandTester->setInputs(['no']);
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Executing OpenSearch Migrations', $output);
        $this->assertStringContainsString('○ Version20240101000000', $output);
        $this->assertStringContainsString('Migration cancelled.', $output);
    }

    public function testMigrateWithExecutionError(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
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

        $migrationManager
            ->expects($this->once())
            ->method('executePendingMigrations')
            ->willThrowException(new \Exception('Migration execution failed'));

        // Act
        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Executing OpenSearch Migrations', $output);
        $this->assertStringContainsString('Error executing migrations: Migration execution failed', $output);
    }

    public function testMigrateWithNoExecutedMigrations(): void
    {
        $migrationManager = $this->createMockWithExpectations(OpenSearchMigrationManager::class);
        $this->migrationManager = $migrationManager;
        $this->buildCommand();

        // Arrange
        $availableMigrations = [
            'Version20240101000000' => 'OpenSearchMigrations\\Version20240101000000',
        ];

        $executedMigrations = [];
        $executedVersions = []; // No migrations were actually executed

        $migrationManager
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn($availableMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('getExecutedMigrations')
            ->willReturn($executedMigrations);

        $migrationManager
            ->expects($this->once())
            ->method('executePendingMigrations')
            ->willReturn($executedVersions);

        // Act
        $this->commandTester->setInputs(['yes']);
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Executing OpenSearch Migrations', $output);
        $this->assertStringContainsString('No migrations were executed.', $output);
    }
}
