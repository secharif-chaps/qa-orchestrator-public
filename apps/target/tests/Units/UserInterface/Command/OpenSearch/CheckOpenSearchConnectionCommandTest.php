<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command\OpenSearch;

use App\Tests\Utils\MockHelpersTrait;
use App\UserInterface\Command\OpenSearch\CheckOpenSearchConnectionCommand;
use OpenSearch\Client;
use OpenSearch\Namespaces\CatNamespace;
use OpenSearch\Namespaces\ClusterNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CheckOpenSearchConnectionCommand::class)]
class CheckOpenSearchConnectionCommandTest extends TestCase
{
    use MockHelpersTrait;
    private Client $openSearchClient;
    private ClusterNamespace $clusterEndpoint;
    private CatNamespace $catEndpoint;
    private CheckOpenSearchConnectionCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openSearchClient = $this->createStub(Client::class);
        $this->clusterEndpoint = $this->createStub(ClusterNamespace::class);
        $this->catEndpoint = $this->createStub(CatNamespace::class);

        $this->openSearchClient
            ->method('cluster')
            ->willReturn($this->clusterEndpoint);

        $this->openSearchClient
            ->method('cat')
            ->willReturn($this->catEndpoint);

        $this->buildCommand();
    }

    private function buildCommand(): void
    {
        $this->command = new CheckOpenSearchConnectionCommand($this->openSearchClient);

        $application = new Application();
        $application->addCommand($this->command);

        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertEquals('opensearch:check-connection', $this->command->getName());
        $this->assertEquals(
            'Check the connection to OpenSearch and display cluster information',
            $this->command->getDescription()
        );
    }

    public function testSuccessfulConnectionWithFullInformation(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.11.0',
                    'lucene_version' => '9.8.0',
                    'build_hash' => 'abc123',
                    'build_date' => '2024-01-01T00:00:00Z',
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willReturn([
                'status' => 'green',
                'number_of_nodes' => 3,
                'active_shards' => 10,
                'relocating_shards' => 0,
                'initializing_shards' => 0,
                'unassigned_shards' => 0,
            ]);

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willReturn([
                [
                    'index' => 'test-index-1',
                    'health' => 'green',
                    'status' => 'open',
                    'docs.count' => '1000',
                    'store.size' => '1.2mb',
                ],
                [
                    'index' => 'test-index-2',
                    'health' => 'yellow',
                    'status' => 'open',
                    'docs.count' => '500',
                    'store.size' => '600kb',
                ],
            ]);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Connection Check', $output);
        $this->assertStringContainsString('Connection Test', $output);
        $this->assertStringContainsString('Successfully connected to OpenSearch!', $output);
        $this->assertStringContainsString('Cluster Information', $output);
        $this->assertStringContainsString('test-cluster', $output);
        $this->assertStringContainsString('8.11.0', $output);
        $this->assertStringContainsString('9.8.0', $output);
        $this->assertStringContainsString('Cluster Health', $output);
        $this->assertStringContainsString('green', $output);
        $this->assertStringContainsString('3', $output);
        $this->assertStringContainsString('Indices Information', $output);
        $this->assertStringContainsString('Found 2 indices:', $output);
        $this->assertStringContainsString('test-index-1', $output);
        $this->assertStringContainsString('test-index-2', $output);
    }

    public function testSuccessfulConnectionWithNoIndices(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'empty-cluster',
                'version' => [
                    'number' => '8.11.0',
                    'lucene_version' => '9.8.0',
                    'build_hash' => 'abc123',
                    'build_date' => '2024-01-01T00:00:00Z',
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willReturn([
                'status' => 'green',
                'number_of_nodes' => 1,
                'active_shards' => 0,
                'relocating_shards' => 0,
                'initializing_shards' => 0,
                'unassigned_shards' => 0,
            ]);

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willReturn([]);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully connected to OpenSearch!', $output);
        $this->assertStringContainsString('No indices found in the cluster.', $output);
    }

    public function testConnectionFailure(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $openSearchClient->method('cluster')
->willReturn($this->clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($this->catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willThrowException(new \Exception('Connection refused'));

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::FAILURE, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('OpenSearch Connection Check', $output);
        $this->assertStringContainsString('Failed to connect to OpenSearch: Connection refused', $output);
        $this->assertStringContainsString('Please check:', $output);
        $this->assertStringContainsString('OpenSearch service is running', $output);
        $this->assertStringContainsString('Host and port are correct', $output);
        $this->assertStringContainsString('Network connectivity between services', $output);
        $this->assertStringContainsString('Firewall settings', $output);
        $this->assertStringContainsString('OpenSearch configuration', $output);
    }

    public function testClusterHealthError(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.11.0',
                    'lucene_version' => '9.8.0',
                    'build_hash' => 'abc123',
                    'build_date' => '2024-01-01T00:00:00Z',
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willThrowException(new \Exception('Health check failed'));

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willReturn([]);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully connected to OpenSearch!', $output);
        $this->assertStringContainsString('Could not retrieve cluster health: Health check failed', $output);
    }

    public function testIndicesInformationError(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.11.0',
                    'lucene_version' => '9.8.0',
                    'build_hash' => 'abc123',
                    'build_date' => '2024-01-01T00:00:00Z',
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willReturn([
                'status' => 'green',
                'number_of_nodes' => 1,
                'active_shards' => 0,
                'relocating_shards' => 0,
                'initializing_shards' => 0,
                'unassigned_shards' => 0,
            ]);

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willThrowException(new \Exception('Indices check failed'));

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully connected to OpenSearch!', $output);
        $this->assertStringContainsString('Could not retrieve indices information: Indices check failed', $output);
    }

    public function testClusterHealthWithDifferentStatuses(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.11.0',
                    'lucene_version' => '9.8.0',
                    'build_hash' => 'abc123',
                    'build_date' => '2024-01-01T00:00:00Z',
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willReturn([
                'status' => 'yellow',
                'number_of_nodes' => 2,
                'active_shards' => 5,
                'relocating_shards' => 1,
                'initializing_shards' => 0,
                'unassigned_shards' => 2,
            ]);

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willReturn([]);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully connected to OpenSearch!', $output);
        $this->assertStringContainsString('yellow', $output);
        $this->assertStringContainsString('2', $output); // number_of_nodes
        $this->assertStringContainsString('5', $output); // active_shards
        $this->assertStringContainsString('1', $output); // relocating_shards
        $this->assertStringContainsString('2', $output); // unassigned_shards
    }

    public function testManyIndicesTruncation(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.11.0',
                    'lucene_version' => '9.8.0',
                    'build_hash' => 'abc123',
                    'build_date' => '2024-01-01T00:00:00Z',
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willReturn([
                'status' => 'green',
                'number_of_nodes' => 1,
                'active_shards' => 0,
                'relocating_shards' => 0,
                'initializing_shards' => 0,
                'unassigned_shards' => 0,
            ]);

        // Create 15 indices to test truncation
        $indices = [];
        for ($i = 1; $i <= 15; ++$i) {
            $indices[] = [
                'index' => "test-index-{$i}",
                'health' => 'green',
                'status' => 'open',
                'docs.count' => (string) ($i * 100),
                'store.size' => "{$i}mb",
            ];
        }

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willReturn($indices);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Found 15 indices:', $output);
        $this->assertStringContainsString('... and 5 more indices', $output);
    }

    public function testMissingClusterInformation(): void
    {
        $openSearchClient = $this->createMockWithExpectations(Client::class);
        $clusterEndpoint = $this->createMockWithExpectations(ClusterNamespace::class);
        $catEndpoint = $this->createMockWithExpectations(CatNamespace::class);
        $openSearchClient->method('cluster')
->willReturn($clusterEndpoint);
        $openSearchClient->method('cat')
->willReturn($catEndpoint);
        $this->openSearchClient = $openSearchClient;
        $this->clusterEndpoint = $clusterEndpoint;
        $this->catEndpoint = $catEndpoint;
        $this->buildCommand();

        // Arrange
        $openSearchClient
            ->expects($this->once())
            ->method('info')
            ->willReturn([
                'cluster_name' => 'test-cluster',
                'version' => [
                    'number' => '8.11.0',
                    // Missing some version fields
                ],
            ]);

        $clusterEndpoint
            ->expects($this->once())
            ->method('health')
            ->willReturn([
                'status' => 'green',
                'number_of_nodes' => 1,
                // Missing some health fields
            ]);

        $catEndpoint
            ->expects($this->once())
            ->method('indices')
            ->with([
                'format' => 'json',
            ])
            ->willReturn([]);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $this->assertEquals(Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully connected to OpenSearch!', $output);
        $this->assertStringContainsString('test-cluster', $output);
        $this->assertStringContainsString('8.11.0', $output);
        $this->assertStringContainsString('N/A', $output); // For missing fields
    }
}
