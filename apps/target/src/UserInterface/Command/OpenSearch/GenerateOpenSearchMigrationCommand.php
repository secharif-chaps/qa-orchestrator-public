<?php

declare(strict_types=1);

namespace App\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationFinder;
use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'make:opensearch:migration', description: 'Generate a new OpenSearch migration')]
class GenerateOpenSearchMigrationCommand extends Command
{
    public function __construct(
        private readonly OpenSearchMigrationFinder $migrationFinder,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('description', InputArgument::OPTIONAL, 'Migration description', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ('dev' !== $this->environment) {
            $io->error('This command cannot be executed in production environment.');

            return Command::FAILURE;
        }

        $description = $input->getArgument('description') ?? 'TODO: Add description';

        $version = date('YmdHis');
        $className = "Version{$version}";
        $fileName = "{$className}.php";

        $migrationDir = $this->migrationFinder->getMigrationDirectory();

        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0o755, true);
        }

        $filePath = $migrationDir . '/' . $fileName;

        if (file_exists($filePath)) {
            $io->error("Migration file already exists: {$fileName}");

            return Command::FAILURE;
        }

        $template = $this->generateMigrationTemplate($className, $description);
        file_put_contents($filePath, $template);

        $io->success("Generated migration: {$fileName}");
        $io->text("File location: {$filePath}");

        return Command::SUCCESS;
    }

    private function getMigrationNamespace(): string
    {
        return OpenSearchMigrationManager::MIGRATION_NAMESPACE;
    }

    private function generateMigrationTemplate(string $className, string $description): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$this->getMigrationNamespace()};

            use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

            /**
             * {$description}
             */
            class {$className} extends AbstractOpenSearchMigration
            {
                public function getDescription(): string
                {
                    return '{$description}';
                }

                public function up(): void
                {
                    // TODO: Implement migration logic
                    // Examples:

                    // Create a new index:
                    // \$this->createIndex('my_index', [
                    //     'mappings' => [
                    //         'properties' => [
                    //             'field1' => ['type' => 'text'],
                    //             'field2' => ['type' => 'keyword'],
                    //         ],
                    //     ],
                    // ]);

                    // Update an existing index:
                    // \$this->updateIndex('my_index', [
                    //     'mappings' => [
                    //         'properties' => [
                    //             'newField' => ['type' => 'keyword'],
                    //         ],
                    //     ],
                    // ]);

                    // Delete an index:
                    // \$this->deleteIndex('my_index');
                }

                public function down(): void
                {
                    // TODO: Implement rollback logic
                    // Examples:

                    // If you created an index in up():
                    // \$this->deleteIndex('my_index');

                    // If you updated an index in up():
                    // Note: Rolling back field additions requires manual intervention
                    // \$this->logger->warning('Rollback for field changes requires manual intervention');

                    // If you deleted an index in up():
                    // You would need to recreate it with the previous schema
                }
            }
            PHP;
    }
}
