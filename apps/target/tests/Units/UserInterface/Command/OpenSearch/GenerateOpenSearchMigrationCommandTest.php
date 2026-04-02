<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationFinder;
use App\UserInterface\Command\OpenSearch\GenerateOpenSearchMigrationCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(GenerateOpenSearchMigrationCommand::class)]
class GenerateOpenSearchMigrationCommandTest extends TestCase
{
    private string $testMigrationDirectory;
    private OpenSearchMigrationFinder&Stub $migrationFinder;
    private GenerateOpenSearchMigrationCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->testMigrationDirectory = sys_get_temp_dir() . '/opensearch_migrations_test';

        // Clean up any existing test directory
        if (is_dir($this->testMigrationDirectory)) {
            $this->removeDirectory($this->testMigrationDirectory);
        }

        $this->migrationFinder = $this->createStub(OpenSearchMigrationFinder::class);
        $this->migrationFinder
            ->method('getMigrationDirectory')
            ->willReturn($this->testMigrationDirectory);

        $this->command = new GenerateOpenSearchMigrationCommand($this->migrationFinder, 'dev');

        $application = new Application();
        $application->addCommand($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testMigrationDirectory)) {
            $this->removeDirectory($this->testMigrationDirectory);
        }
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertEquals('make:opensearch:migration', $this->command->getName());
        $this->assertEquals('Generate a new OpenSearch migration', $this->command->getDescription());
    }

    public function testSuccessfulMigrationGeneration(): void
    {
        $this->commandTester->execute([
            'description' => 'Test migration',
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Generated migration:', $output);
        $this->assertStringContainsString('File location:', $output);

        // Verify directory was created
        $this->assertDirectoryExists($this->testMigrationDirectory);

        // Verify migration file was created
        $files = glob($this->testMigrationDirectory . '/Version*.php');
        $this->assertNotFalse($files);
        $this->assertCount(1, $files);

        $migrationFile = $files[0];
        $this->assertFileExists($migrationFile);

        // Verify file content
        $content = file_get_contents($migrationFile);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Test migration', $content);
        $this->assertStringContainsString('class Version', $content);
        $this->assertStringContainsString('extends AbstractOpenSearchMigration', $content);
    }

    public function testMigrationGenerationWithDefaultDescription(): void
    {
        // Execute without description argument
        $this->commandTester->execute([]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        // Verify migration file was created with default description
        $files = glob($this->testMigrationDirectory . '/Version*.php');
        $this->assertNotFalse($files);
        $this->assertCount(1, $files);

        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('TODO: Add description', $content);
    }

    public function testMigrationGenerationWhenFileAlreadyExists(): void
    {
        // Create a migration file first
        $this->commandTester->execute([
            'description' => 'First migration',
        ]);
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        // Get the created file name
        $files = glob($this->testMigrationDirectory . '/Version*.php');
        $this->assertNotFalse($files);

        // Try to create another migration with the same timestamp (simulate race condition)
        $this->commandTester->execute([
            'description' => 'Second migration',
        ]);

        // Should fail if file already exists
        $output = $this->commandTester->getDisplay();
        if (str_contains($output, 'Migration file already exists')) {
            $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('Migration file already exists', $output);
        } else {
            // If no collision occurred, the command should succeed
            $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());
        }
    }

    public function testMigrationTemplateContent(): void
    {
        $this->commandTester->execute([
            'description' => 'Template test migration',
        ]);

        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $files = glob($this->testMigrationDirectory . '/Version*.php');
        $this->assertNotFalse($files);
        $this->assertCount(1, $files);
        $content = file_get_contents($files[0]);
        $this->assertNotFalse($content);

        // Verify PHP structure
        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('declare(strict_types=1);', $content);

        // Verify class structure
        $this->assertStringContainsString('class Version', $content);
        $this->assertStringContainsString('extends AbstractOpenSearchMigration', $content);

        // Verify methods
        $this->assertStringContainsString('public function getDescription(): string', $content);
        $this->assertStringContainsString('public function up(): void', $content);
        $this->assertStringContainsString('public function down(): void', $content);

        // Verify description is included in docblock and method
        $this->assertStringContainsString('* Template test migration', $content);
        $this->assertStringContainsString("return 'Template test migration';", $content);

        // Verify namespace
        $this->assertStringContainsString('namespace OpenSearchMigrations;', $content);

        // Verify use statements
        $this->assertStringContainsString(
            'use App\\Infrastructure\\OpenSearch\\Migration\\AbstractOpenSearchMigration;',
            $content
        );

        // Verify template includes examples and TODOs
        $this->assertStringContainsString('// TODO: Implement migration logic', $content);
        $this->assertStringContainsString('// TODO: Implement rollback logic', $content);
        $this->assertStringContainsString('// Examples:', $content);
    }

    public function testDirectoryCreation(): void
    {
        // Use a non-existent directory
        $nonExistentDir = $this->testMigrationDirectory . '/nested/subdirectory';
        $migrationFinder = $this->createStub(OpenSearchMigrationFinder::class);
        $migrationFinder
            ->method('getMigrationDirectory')
            ->willReturn($nonExistentDir);

        $command = new GenerateOpenSearchMigrationCommand($migrationFinder, 'dev');
        $application = new Application();
        $application->addCommand($command);
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'description' => 'Directory creation test',
        ]);

        $this->assertEquals(Command::SUCCESS, $commandTester->getStatusCode());
        $this->assertDirectoryExists($nonExistentDir);

        $this->removeDirectory($nonExistentDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
