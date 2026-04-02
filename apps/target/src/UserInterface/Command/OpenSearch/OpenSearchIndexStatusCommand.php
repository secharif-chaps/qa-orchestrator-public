<?php

declare(strict_types=1);

namespace App\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'opensearch:index:status', description: 'Show status of OpenSearch index aliases')]
class OpenSearchIndexStatusCommand extends Command
{
    public function __construct(
        private readonly OpenSearchMigrationManager $migrationManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('alias', InputArgument::OPTIONAL, 'Index alias to check (optional)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $alias = $input->getArgument('alias');

        if ($alias) {
            return $this->showAliasStatus($io, $alias);
        }

        return $this->showAllAliasesStatus($io);
    }

    private function showAliasStatus(SymfonyStyle $io, string $alias): int
    {
        $io->title("Status for index alias: {$alias}");

        try {
            $currentVersion = $this->migrationManager->getCurrentIndexVersion($alias);
            $indices = $this->migrationManager->getIndicesForAlias($alias);

            if (empty($indices)) {
                $io->warning("No indices found for alias: {$alias}");

                return Command::SUCCESS;
            }

            $io->section('Current Status');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Current Version', $currentVersion ?? 'None'],
                    ['Indices', implode(', ', $indices)],
                    ['Index Count', (string) \count($indices)],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error getting status for alias {$alias}: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function showAllAliasesStatus(SymfonyStyle $io): int
    {
        $io->title('OpenSearch Index Aliases Status');

        try {
            // Get all available migrations to find aliases
            $availableMigrations = $this->migrationManager->getAvailableMigrations();
            $executedMigrations = $this->migrationManager->getExecutedMigrations();

            if (empty($availableMigrations)) {
                $io->text('No migrations found.');

                return Command::SUCCESS;
            }

            // For now, we'll show a simplified status since migrations no longer declare their aliases
            $io->section('Available Migrations');
            $migrationData = [];
            foreach ($availableMigrations as $version => $migrationClass) {
                try {
                    $migration = new $migrationClass();

                    $migrationData[] = [
                        \in_array($version, $executedMigrations) ? '✓' : '○',
                        $version,
                        $migration->getDescription(),
                    ];
                } catch (\Exception $e) {
                    $migrationData[] = ['○', $version, 'Error: Could not instantiate migration'];
                }
            }

            $io->table(['Status', 'Version', 'Description'], $migrationData);

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error getting aliases status: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
