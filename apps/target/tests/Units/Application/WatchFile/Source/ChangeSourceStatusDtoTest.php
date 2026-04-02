<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Domain\Source\SourceStatus;
use App\UserInterface\Dto\Source\ChangeSourceStatusDto;
use PHPUnit\Framework\TestCase;

class ChangeSourceStatusDtoTest extends TestCase
{
    public function testChangeSourceStatusDtoWithEnabledStatus(): void
    {
        $dto = new ChangeSourceStatusDto('active');

        $this->assertEquals('active', $dto->status);
        $this->assertEquals(SourceStatus::ACTIVE, $dto->getStatus());
    }

    public function testChangeSourceStatusDtoWithDisabledStatus(): void
    {
        $dto = new ChangeSourceStatusDto('inactive');

        $this->assertEquals('inactive', $dto->status);
        $this->assertEquals(SourceStatus::INACTIVE, $dto->getStatus());
    }
}
