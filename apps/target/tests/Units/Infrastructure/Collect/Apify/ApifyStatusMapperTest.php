<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\CollectTaskStatus;
use App\Infrastructure\Collect\Apify\ApifyStatusMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApifyStatusMapper::class)]
class ApifyStatusMapperTest extends TestCase
{
    private ApifyStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ApifyStatusMapper();
    }

    #[DataProvider('statusMappingProvider')]
    public function testMapStatus(string $providerStatus, CollectTaskStatus $expectedStatus): void
    {
        $actualStatus = $this->mapper->mapStatus($providerStatus);
        $this->assertSame($expectedStatus, $actualStatus);
    }

    /**
     * @return \Generator<string, array{string, CollectTaskStatus}>
     */
    public static function statusMappingProvider(): \Generator
    {
        yield 'READY maps to QUEUED' => ['READY', CollectTaskStatus::QUEUED];
        yield 'RUNNING maps to RUNNING' => ['RUNNING', CollectTaskStatus::RUNNING];
        yield 'TIMING-OUT maps to RUNNING' => ['TIMING-OUT', CollectTaskStatus::RUNNING];
        yield 'ABORTING maps to RUNNING' => ['ABORTING', CollectTaskStatus::RUNNING];
        yield 'SUCCEEDED maps to COMPLETED' => ['SUCCEEDED', CollectTaskStatus::COMPLETED];
        yield 'FAILED maps to FAILED' => ['FAILED', CollectTaskStatus::FAILED];
        yield 'TIMED-OUT maps to FAILED' => ['TIMED-OUT', CollectTaskStatus::FAILED];
        yield 'ABORTED maps to CANCELLED' => ['ABORTED', CollectTaskStatus::CANCELLED];
        yield 'unknown status maps to FAILED' => ['SOME_UNKNOWN_STATUS', CollectTaskStatus::FAILED];
        yield 'empty string maps to FAILED' => ['', CollectTaskStatus::FAILED];
    }
}
