<?php

declare(strict_types=1);

namespace App\UserInterface\Command\OpenSearch;

use OpenSearch\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'opensearch:check-connection',
    description: 'Check the connection to OpenSearch and display cluster information',
)]
class CheckOpenSearchConnectionCommand extends Command
{
    public function __construct(
        private readonly Client $openSearchClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            This command checks the connection to OpenSearch and displays cluster information.

            Examples:
              <info>php bin/console app:opensearch:check-connection</info>
            HELP
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('OpenSearch Connection Check');

        try {
            $io->section('Connection Test');
            $io->text('Attempting to connect to OpenSearch...');

            // Test basic connection with info endpoint
            $info = $this->openSearchClient->info();

            $io->success('Successfully connected to OpenSearch!');

            $io->section('Cluster Information');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Cluster Name', $info['cluster_name'] ?? 'N/A'],
                    ['Version', $info['version']['number'] ?? 'N/A'],
                    ['Lucene Version', $info['version']['lucene_version'] ?? 'N/A'],
                    ['Build Hash', $info['version']['build_hash'] ?? 'N/A'],
                    ['Build Date', $info['version']['build_date'] ?? 'N/A'],
                ]
            );

            // Test cluster health
            $io->section('Cluster Health');
            try {
                $health = $this->openSearchClient->cluster()
->health();
                $statusColor = match ($health['status']) {
                    'green' => 'green',
                    'yellow' => 'yellow',
                    'red' => 'red',
                    default => 'white',
                };

                $io->table(
                    ['Property', 'Value'],
                    [
                        ['Status', "<fg={$statusColor}>{$health['status']}</>"],
                        ['Number of Nodes', $health['number_of_nodes'] ?? 'N/A'],
                        ['Active Shards', $health['active_shards'] ?? 'N/A'],
                        ['Relocating Shards', $health['relocating_shards'] ?? 'N/A'],
                        ['Initializing Shards', $health['initializing_shards'] ?? 'N/A'],
                        ['Unassigned Shards', $health['unassigned_shards'] ?? 'N/A'],
                    ]
                );
            } catch (\Exception $e) {
                $io->warning('Could not retrieve cluster health: ' . $e->getMessage());
            }

            // Test indices listing
            $io->section('Indices Information');
            try {
                $indices = $this->openSearchClient->cat()
->indices([
    'format' => 'json',
]);
                if (empty($indices)) {
                    $io->text('No indices found in the cluster.');
                } else {
                    $io->text(\sprintf('Found %d indices:', \count($indices)));
                    $io->table(
                        ['Index', 'Health', 'Status', 'Docs Count', 'Store Size'],
                        array_map(
                            fn ($index) => [
                                $index['index'] ?? 'N/A',
                                $index['health'] ?? 'N/A',
                                $index['status'] ?? 'N/A',
                                $index['docs.count'] ?? 'N/A',
                                $index['store.size'] ?? 'N/A',
                            ],
                            \array_slice($indices, 0, 10) // Show first 10 indices
                        )
                    );

                    if (\count($indices) > 10) {
                        $io->text(\sprintf('... and %d more indices', \count($indices) - 10));
                    }
                }
            } catch (\Exception $e) {
                $io->warning('Could not retrieve indices information: ' . $e->getMessage());
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to connect to OpenSearch: ' . $e->getMessage());
            $io->text('Please check:');
            $io->listing([
                'OpenSearch service is running',
                'Host and port are correct',
                'Network connectivity between services',
                'Firewall settings',
                'OpenSearch configuration',
            ]);

            return Command::FAILURE;
        }
    }
}
