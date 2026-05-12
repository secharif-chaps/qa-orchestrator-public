<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\OpenSearch\Migration;

use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationHealthChecker;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(OpenSearchMigrationHealthChecker::class)]
class OpenSearchMigrationHealthCheckerTest extends TestCase
{
    private Client&MockObject $openSearchClient;
    private IndicesNamespace&MockObject $indicesEndpoint;
    private OpenSearchMigrationHealthChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openSearchClient = $this->createMock(Client::class);
        $this->indicesEndpoint = $this->createMock(IndicesNamespace::class);

        $this->openSearchClient
            ->method('indices')
            ->willReturn($this->indicesEndpoint);

        $this->checker = new OpenSearchMigrationHealthChecker($this->openSearchClient);
    }

    public function testEnsureAliasesExistPassesWhenAllAliasesExist(): void
    {
        $this->indicesEndpoint
            ->expects($this->exactly(2))
            ->method('existsAlias')
            ->willReturn(true);

        $this->checker->ensureAliasesExist(['document', 'watch_file_events']);
    }

    public function testEnsureAliasesExistThrowsWhenOneAliasIsMissing(): void
    {
        $this->indicesEndpoint
            ->method('existsAlias')
            ->willReturnMap([
                [[
                    'name' => 'document',
                ], true],
                [[
                    'name' => 'watch_file_events',
                ], false],
            ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('watch_file_events');

        $this->checker->ensureAliasesExist(['document', 'watch_file_events']);
    }

    public function testEnsureAliasesExistThrowsWithAllMissingAliasesInMessage(): void
    {
        $this->indicesEndpoint
            ->method('existsAlias')
            ->willReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('document');
        $this->expectExceptionMessage('watch_file_events');

        $this->checker->ensureAliasesExist(['document', 'watch_file_events']);
    }

    public function testEnsureAliasesExistThrowsWhenClientThrows(): void
    {
        $this->indicesEndpoint
            ->method('existsAlias')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing aliases');

        $this->checker->ensureAliasesExist(['document']);
    }

    public function testEnsureAliasesExistPassesForEmptyList(): void
    {
        $this->indicesEndpoint
            ->expects($this->never())
            ->method('existsAlias');

        $this->checker->ensureAliasesExist([]);
    }
}
