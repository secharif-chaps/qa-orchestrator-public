<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus;

use App\Domain\Collect\CollectTaskStatus;
use App\Infrastructure\Collect\Bakus\BakusStatusMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BakusStatusMapperTest extends TestCase
{
    private BakusStatusMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new BakusStatusMapper();
    }

    #[DataProvider('statusMappingProvider')]
    public function testMapStatus(string $providerStatus, CollectTaskStatus $expectedStatus): void
    {
        $actualStatus = $this->mapper->mapStatus($providerStatus);
        $this->assertSame($expectedStatus, $actualStatus);
    }

    /** @return \Generator<string, array{string, CollectTaskStatus}> */
    public static function statusMappingProvider(): \Generator
    {
        yield 'pending status' => ['pending', CollectTaskStatus::QUEUED];
        yield 'in_progress status' => ['in_progress', CollectTaskStatus::RUNNING];
        yield 'done status' => ['done', CollectTaskStatus::COMPLETED];
        yield 'canceled status' => ['canceled', CollectTaskStatus::CANCELLED];
        yield 'deleted status' => ['deleted', CollectTaskStatus::CANCELLED];
        yield 'cancelled status (british spelling)' => ['cancelled', CollectTaskStatus::CANCELLED];
        yield 'unknown status maps to failed' => ['some_unknown_status', CollectTaskStatus::FAILED];
        yield 'error status maps to failed' => ['error', CollectTaskStatus::FAILED];
    }
}
