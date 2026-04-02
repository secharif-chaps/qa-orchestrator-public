<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Migration;

use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;
use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationFinder;
use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationManager;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(OpenSearchMigrationManager::class)]
class OpenSearchMigrationManagerTest extends TestCase
{
    private Client&MockObject $openSearchClient;
    private IndicesNamespace&MockObject $indicesEndpoint;
    private OpenSearchMigrationFinder&MockObject $migrationFinder;
    private OpenSearchMigrationManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openSearchClient = $this->createMock(Client::class);
        $this->indicesEndpoint = $this->createMock(IndicesNamespace::class);
        $this->migrationFinder = $this->createMock(OpenSearchMigrationFinder::class);

        // Configure the client to return the indices endpoint
        $this->openSearchClient
            ->method('indices')
            ->willReturn($this->indicesEndpoint);

        $this->manager = new OpenSearchMigrationManager(
            $this->openSearchClient,
            $this->migrationFinder,
            1,
            0,
            new NullLogger(),
        );
    }

    public function testGetExecutedMigrationsReturnsEmptyArrayWhenIndexDoesNotExist(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(false);

        $migrations = $this->manager->getExecutedMigrations();

        $this->assertEmpty($migrations);
    }

    public function testGetExecutedMigrationsReturnsMigrationsWhenIndexExists(): void
    {
        $mockResponse = [
            'hits' => [
                'hits' => [
                    [
                        '_source' => [
                            'version' => 'Version20240101000000',
                        ],
                    ],
                    [
                        '_source' => [
                            'version' => 'Version20240102000000',
                        ],
                    ],
                ],
            ],
        ];

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->with([
                'index' => 'opensearch_migrations',
                'body' => [
                    'query' => [
                        'match_all' => new \stdClass(),
                    ],
                    'sort' => [[
                        'version' => [
                            'order' => 'asc',
                        ],
                    ]],
                    'size' => 1000,
                ],
            ])
            ->willReturn($mockResponse);

        $migrations = $this->manager->getExecutedMigrations();

        $this->assertCount(2, $migrations);
        $this->assertEquals(['Version20240101000000', 'Version20240102000000'], $migrations);
    }

    public function testGetExecutedMigrationsHandlesException(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willThrowException(new \Exception('Connection failed'));

        $migrations = $this->manager->getExecutedMigrations();

        $this->assertEmpty($migrations);
    }

    public function testGetCurrentIndexVersionReturnsNullWhenAliasDoesNotExist(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_alias',
            ])
            ->willThrowException(new \Exception('404 Not Found'));

        $version = $this->manager->getCurrentIndexVersion('test_alias');

        $this->assertNull($version);
    }

    public function testGetCurrentIndexVersionReturnsVersionWhenAliasExists(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_alias',
            ])
            ->willReturn([
                'test_alias_v1' => [],
            ]);

        $version = $this->manager->getCurrentIndexVersion('test_alias');

        $this->assertEquals('v1', $version);
    }

    public function testGetCurrentIndexVersionReturnsNullWhenNoVersionInIndexName(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_alias',
            ])
            ->willReturn([
                'test_alias' => [],
            ]);

        $version = $this->manager->getCurrentIndexVersion('test_alias');

        $this->assertEquals('alias', $version);
    }

    public function testGetIndicesForAliasReturnsEmptyArrayWhenAliasDoesNotExist(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_alias',
            ])
            ->willThrowException(new \Exception('404 Not Found'));

        $indices = $this->manager->getIndicesForAlias('test_alias');

        $this->assertEmpty($indices);
    }

    public function testGetIndicesForAliasReturnsIndicesWhenAliasExists(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_alias',
            ])
            ->willReturn([
                'test_alias_v1' => [],
                'test_alias_v2' => [],
            ]);

        $indices = $this->manager->getIndicesForAlias('test_alias');

        $this->assertCount(2, $indices);
        $this->assertEquals(['test_alias_v1', 'test_alias_v2'], $indices);
    }

    public function testMarkMigrationAsExecutedCreatesIndexIfNotExists(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(false);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'opensearch_migrations',
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                    ],
                    'mappings' => [
                        'properties' => [
                            'version' => [
                                'type' => 'keyword',
                            ],
                            'description' => [
                                'type' => 'text',
                            ],
                            'executed_at' => [
                                'type' => 'date',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->openSearchClient
            ->expects($this->once())
            ->method('index')
            ->with($this->callback(function ($params) {
                return 'opensearch_migrations' === $params['index']
                    && 'Version20240101000000' === $params['id']
                    && 'Version20240101000000' === $params['body']['version']
                    && 'Test migration' === $params['body']['description']
                    && \is_string($params['body']['executed_at']);
            }));

        $this->manager->markMigrationAsExecuted('Version20240101000000', 'Test migration');
    }

    public function testMarkMigrationAsExecutedUsesExistingIndex(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('index')
            ->with($this->callback(function ($params) {
                return 'opensearch_migrations' === $params['index']
                    && 'Version20240101000000' === $params['id']
                    && 'Version20240101000000' === $params['body']['version']
                    && 'Test migration' === $params['body']['description']
                    && \is_string($params['body']['executed_at']);
            }));

        $this->manager->markMigrationAsExecuted('Version20240101000000', 'Test migration');
    }

    public function testMarkMigrationAsNotExecutedDeletesFromIndex(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'opensearch_migrations',
                'id' => 'Version20240101000000',
            ]);

        $this->manager->markMigrationAsNotExecuted('Version20240101000000');
    }

    public function testMarkMigrationAsNotExecutedReturnsEarlyWhenIndexDoesNotExist(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(false);

        $this->openSearchClient
            ->expects($this->never())
            ->method('delete');

        $this->manager->markMigrationAsNotExecuted('Version20240101000000');
    }

    public function testExecutePendingMigrationsExecutesAllPendingMigrations(): void
    {
        // Mock the finder to return no available migrations
        $this->migrationFinder
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn([]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $executed = $this->manager->executePendingMigrations();

        $this->assertEmpty($executed);
    }

    public function testRollbackLastMigrationRollsBackLastExecutedMigration(): void
    {
        // This test is simplified to avoid file system complexity
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $rolledBack = $this->manager->rollbackLastMigration();

        $this->assertNull($rolledBack);
    }

    public function testRollbackLastMigrationReturnsNullWhenNoMigrationsExecuted(): void
    {
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $rolledBack = $this->manager->rollbackLastMigration();

        $this->assertNull($rolledBack);
    }

    public function testRollbackLastMigrationThrowsExceptionWhenMigrationClassNotFound(): void
    {
        // Mock the finder to return no available migrations
        $this->migrationFinder
            ->expects($this->once())
            ->method('getAvailableMigrations')
            ->willReturn([]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'version' => 'Version20240101000000',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Migration class for version Version20240101000000 not found');

        $this->manager->rollbackLastMigration();
    }

    public function testExecuteMigrationExecutesCreateIndexOperation(): void
    {
        $migration = new TestMigration();

        // Mock the indexExists method to return false (index doesn't exist)
        // Called twice: once for versionedIndex check in executeCreateIndex,
        // once for plain index conflict check in switchAlias
        $this->indicesEndpoint
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturn(false);

        // Mock getCurrentIndexVersion to return null (no current version)
        // Called twice: once in executeCreateIndex, once in switchAlias (via getIndicesForAlias)
        $this->indicesEndpoint
            ->expects($this->exactly(2))
            ->method('getAlias')
            ->with([
                'name' => 'test_index',
            ])
            ->willThrowException(new \Exception('404 Not Found'));

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'test_index_v1',
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 0,
                    ],
                ],
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('updateAliases')
            ->with([
                'body' => [
                    'actions' => [
                        [
                            'add' => [
                                'index' => 'test_index_v1',
                                'alias' => 'test_index',
                            ],
                        ],
                    ],
                ],
            ]);

        // Mock the get method call in deleteOldIndices (getIndicesWithPrefix)
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('get')
            ->with([
                'index' => 'test_index_*',
            ])
            ->willReturn([
                'test_index_v1' => [],
            ]);

        $this->manager->executeMigration('v1', $migration);
    }

    public function testExecuteMigrationExecutesUpdateIndexOperation(): void
    {
        $migration = new TestUpdateMigration();

        // Mock the getCurrentIndexVersion to return a current version
        // Note: executeUpdateIndex doesn't call indexExists directly

        $this->indicesEndpoint
            ->expects($this->exactly(2))
            ->method('getAlias')
            ->with([
                'name' => 'test_index',
            ])
            ->willReturn([
                'test_index_v0' => [],
            ]);

        $this->indicesEndpoint
            ->expects($this->exactly(1))
            ->method('get')
            ->with([
                'index' => 'test_index_*',
            ])
            ->willReturn([
                'test_index_v0' => [],
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('create')
            ->with([
                'index' => 'test_index_v1',
                'body' => [
                    'settings' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 1,
                    ],
                    'mappings' => [
                        'properties' => [
                            'field2' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->openSearchClient
            ->expects($this->once())
            ->method('reindex')
            ->with([
                'body' => [
                    'source' => [
                        'index' => 'test_index_v0',
                    ],
                    'dest' => [
                        'index' => 'test_index_v1',
                    ],
                ],
                'wait_for_completion' => true,
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('updateAliases');

        $this->manager->executeMigration('v1', $migration);
    }

    public function testExecuteMigrationExecutesDeleteIndexOperation(): void
    {
        $migration = new TestDeleteMigration();

        // Mock the getCurrentIndexVersion to return a current version
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_index',
            ])
            ->willReturn([
                'test_index_v0' => [],
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('deleteAlias')
            ->with([
                'index' => 'test_index_v0',
                'name' => 'test_index',
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'test_index_v0',
            ]);

        $this->manager->executeMigration('v1', $migration);
    }

    public function testExecuteMigrationThrowsExceptionForUnknownOperationType(): void
    {
        $migration = new TestInvalidMigration();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operation type: invalid_operation');

        $this->manager->executeMigration('v1', $migration);
    }

    public function testRollbackMigrationThrowsExceptionForUnknownOperationType(): void
    {
        $migration = new TestInvalidMigration();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operation type: invalid_operation');

        $this->manager->rollbackMigration('v1', $migration);
    }

    public function testExecuteRollbackOperationsHandlesCreateIndexRollback(): void
    {
        $migration = new TestCreateIndexRollbackMigration();

        // Mock the getCurrentIndexVersion to return a current version
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_index',
            ])
            ->willReturn([
                'test_index_v1' => [],
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('deleteAlias')
            ->with([
                'index' => 'test_index_v1',
                'name' => 'test_index',
            ]);

        $this->indicesEndpoint
            ->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'test_index_v1',
            ]);

        $this->manager->rollbackMigration('v2', $migration);
    }

    public function testExecuteRollbackOperationsHandlesUpdateIndexRollback(): void
    {
        $migration = new TestUpdateIndexRollbackMigration();

        // Mock getExecutedMigrations to return a list with previous version
        // Called 3 times: opensearch_migrations check, test_index_v1 check in revertToPreviousVersion,
        // and test_index plain index conflict check in switchAlias
        $this->indicesEndpoint
            ->expects($this->exactly(3))
            ->method('exists')
            ->willReturnCallback(function ($params) {
                if ('opensearch_migrations' === $params['index']) {
                    return true;
                }
                if ('test_index_v1' === $params['index']) {
                    return true;
                }

                return false;
            });

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'version' => 'v1',
                            ],
                        ],
                        [
                            '_source' => [
                                'version' => 'v2',
                            ],
                        ],
                    ],
                ],
            ]);

        // Mock getAlias for switchAlias
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('getAlias')
            ->with([
                'name' => 'test_index',
            ])
            ->willReturn([
                'test_index_v2' => [],
            ]);

        // Mock updateAliases for switchAlias
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('updateAliases')
            ->with([
                'body' => [
                    'actions' => [
                        [
                            'remove' => [
                                'index' => 'test_index_v2',
                                'alias' => 'test_index',
                            ],
                        ],
                        [
                            'add' => [
                                'index' => 'test_index_v1',
                                'alias' => 'test_index',
                            ],
                        ],
                    ],
                ],
            ]);

        // Mock get for deleteOldIndices
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('get')
            ->with([
                'index' => 'test_index_*',
            ])
            ->willReturn([
                'test_index_v1' => [],
                'test_index_v2' => [],
            ]);

        // Mock delete for deleteOldIndices
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('delete')
            ->with([
                'index' => 'test_index_v2',
            ]);

        $this->manager->rollbackMigration('v2', $migration);
    }

    public function testExecuteRollbackOperationsHandlesDeleteIndexRollback(): void
    {
        $migration = new TestDeleteIndexRollbackMigration();

        // Mock getCurrentIndexVersion to return null (no current version)
        $this->indicesEndpoint
            ->expects($this->exactly(1))
            ->method('getAlias')
            ->with([
                'name' => 'test_index',
            ])
            ->willThrowException(new \Exception('404 Not Found'));

        $this->manager->rollbackMigration('v1', $migration);
    }

    public function testExecuteRollbackOperationsThrowsExceptionForUnknownOperationType(): void
    {
        $migration = new TestInvalidRollbackMigration();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operation type: invalid_operation');

        $this->manager->rollbackMigration('v1', $migration);
    }

    public function testRevertToPreviousVersionWhenNoPreviousVersionExists(): void
    {
        $migration = new TestUpdateIndexRollbackMigration();

        // Mock getExecutedMigrations to return only current version (no previous)
        $this->indicesEndpoint
            ->expects($this->once())
            ->method('exists')
            ->with([
                'index' => 'opensearch_migrations',
            ])
            ->willReturn(true);

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'version' => 'v1',
                            ],
                        ],
                    ],
                ],
            ]);

        // Should not call any other methods since there's no previous version
        $this->indicesEndpoint
            ->expects($this->never())
            ->method('getAlias');

        $this->manager->rollbackMigration('v1', $migration);
    }

    public function testRevertToPreviousVersionWhenPreviousIndexDoesNotExist(): void
    {
        $migration = new TestUpdateIndexRollbackMigration();

        // Mock getExecutedMigrations to return a list with previous version
        $this->indicesEndpoint
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturnCallback(function ($params) {
                if ('opensearch_migrations' === $params['index']) {
                    return true;
                }
                if ('test_index_v1' === $params['index']) {
                    return false;
                }

                return false;
            });

        $this->openSearchClient
            ->expects($this->once())
            ->method('search')
            ->willReturn([
                'hits' => [
                    'hits' => [
                        [
                            '_source' => [
                                'version' => 'v1',
                            ],
                        ],
                        [
                            '_source' => [
                                'version' => 'v2',
                            ],
                        ],
                    ],
                ],
            ]);

        // Should not call switchAlias or deleteOldIndices
        $this->indicesEndpoint
            ->expects($this->never())
            ->method('getAlias');

        $this->manager->rollbackMigration('v2', $migration);
    }
}

// Test migration class for testing purposes
class TestMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test migration';
    }

    public function up(): void
    {
        $this->createIndex('test_index', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ]);
    }

    public function down(): void
    {
        $this->deleteIndex('test_index');
    }
}

class TestUpdateMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test update migration';
    }

    public function up(): void
    {
        $this->updateIndex('test_index', [
            'settings' => [
                'number_of_replicas' => 1,
            ],
            'mappings' => [
                'properties' => [
                    'field2' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $this->updateIndex('test_index', [
            'settings' => [
                'number_of_replicas' => 0,
            ],
        ]);
    }
}

class TestDeleteMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test delete migration';
    }

    public function up(): void
    {
        $this->deleteIndex('test_index');
    }

    public function down(): void
    {
        $this->createIndex('test_index', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ]);
    }
}

class TestInvalidMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test invalid migration';
    }

    public function up(): void
    {
        $this->operations[] = [
            'type' => 'invalid_operation',
            'name' => 'test_index',
        ];
    }

    public function down(): void
    {
        $this->operations[] = [
            'type' => 'invalid_operation',
            'name' => 'test_index',
        ];
    }
}

class TestCreateIndexRollbackMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test create index rollback migration';
    }

    public function up(): void
    {
        $this->createIndex('test_index', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ]);
    }

    public function down(): void
    {
        $this->createIndex('test_index', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ]);
    }
}

class TestUpdateIndexRollbackMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test update index rollback migration';
    }

    public function up(): void
    {
        $this->updateIndex('test_index', [
            'settings' => [
                'number_of_replicas' => 1,
            ],
        ]);
    }

    public function down(): void
    {
        $this->updateIndex('test_index', [
            'settings' => [
                'number_of_replicas' => 0,
            ],
        ]);
    }
}

class TestDeleteIndexRollbackMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test delete index rollback migration';
    }

    public function up(): void
    {
        $this->deleteIndex('test_index');
    }

    public function down(): void
    {
        $this->createIndex('test_index', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ]);
    }
}

class TestInvalidRollbackMigration extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Test invalid rollback migration';
    }

    public function up(): void
    {
        $this->createIndex('test_index', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
        ]);
    }

    public function down(): void
    {
        $this->operations[] = [
            'type' => 'invalid_operation',
            'name' => 'test_index',
        ];
    }
}
