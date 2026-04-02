<?php

declare(strict_types=1);

namespace App\UserInterface\Command\OpenSearch;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'opensearch:migrations:migrate', description: 'Execute pending OpenSearch migrations')]
class OpenSearchMigrationCommand extends Command
{
    public function __construct(
        private readonly OpenSearchMigrationManager $migrationManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be migrated without executing')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Rollback the last migration')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Show migration status')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Execute migrations without asking for confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('status')) {
            return $this->showStatus($io);
        }

        if ($input->getOption('rollback')) {
            return $this->rollback($io);
        }

        return $this->migrate($input, $io);
    }

    private function showStatus(SymfonyStyle $io): int
    {
        $availableMigrations = $this->migrationManager->getAvailableMigrations();
        $executedMigrations = $this->migrationManager->getExecutedMigrations();
        $pendingMigrations = array_diff(array_keys($availableMigrations), $executedMigrations);

        $io->title('OpenSearch Migration Status');

        $io->section('Executed Migrations');
        if (empty($executedMigrations)) {
            $io->text('No migrations have been executed.');
        } else {
            foreach ($executedMigrations as $version) {
                $io->text("✓ {$version}");
            }
        }

        $io->section('Pending Migrations');
        if (empty($pendingMigrations)) {
            $io->text('No pending migrations.');
        } else {
            foreach ($pendingMigrations as $version) {
                $io->text("○ {$version}");
            }
        }

        return Command::SUCCESS;
    }

    private function rollback(SymfonyStyle $io): int
    {
        $io->title('Rolling Back Last OpenSearch Migration');

        try {
            $rolledBackVersion = $this->migrationManager->rollbackLastMigration();

            if (null === $rolledBackVersion) {
                $io->success('No migrations to rollback.');

                return Command::SUCCESS;
            }

            $io->success("Successfully rolled back migration: {$rolledBackVersion}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error rolling back migration: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    private function migrate(InputInterface $input, SymfonyStyle $io): int
    {
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        if ($dryRun) {
            $io->title('OpenSearch Migration Plan (Dry Run)');
        } else {
            $io->title('Executing OpenSearch Migrations');
        }

        $availableMigrations = $this->migrationManager->getAvailableMigrations();
        $executedMigrations = $this->migrationManager->getExecutedMigrations();
        $pendingMigrations = array_diff(array_keys($availableMigrations), $executedMigrations);

        if (empty($pendingMigrations)) {
            $io->success('No pending migrations.');

            return Command::SUCCESS;
        }

        $io->section('Pending Migrations');
        foreach ($pendingMigrations as $version) {
            $io->text("○ {$version}");
        }

        if ($dryRun) {
            $io->note('This was a dry run. No migrations were executed.');

            return Command::SUCCESS;
        }

        if (!$force && !$io->confirm('Do you want to execute these migrations?', false)) {
            $io->text('Migration cancelled.');

            return Command::SUCCESS;
        }

        if ($force) {
            $io->note('Force mode enabled - executing migrations without confirmation.');
        }

        try {
            $executed = $this->migrationManager->executePendingMigrations();

            if (empty($executed)) {
                $io->success('No migrations were executed.');
            } else {
                $io->success('Successfully executed migrations: ' . implode(', ', $executed));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("Error executing migrations: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
