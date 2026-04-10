<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use App\Domain\Organisation\TenantContext;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use PHPUnit\Framework\TestCase;

class WatchFileActivityLoggerTest extends TestCase
{
    private WatchFileActivityLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new WatchFileActivityLogger($this->createStub(TenantContext::class));
    }

    public function testLogUpdate(): void
    {
        $user = $this->createStub(User::class);
        $watchFile = $this->createStub(WatchFile::class);

        $changes = [
            'old' => [
                'name' => 'Old Name',
            ],
            'new' => [
                'name' => 'New Name',
            ],
        ];

        $activity = $this->logger->logUpdate($watchFile, $user, $changes);

        $this->assertSame($watchFile, $activity->getWatchFile());
        $this->assertSame($user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('changes', $actionData);
        $this->assertEquals($changes, $actionData['changes']);
    }

    public function testLogUpdateWithEmptyChanges(): void
    {
        $user = $this->createStub(User::class);
        $watchFile = $this->createStub(WatchFile::class);

        $changes = [
            'old' => [],
            'new' => [],
        ];

        $activity = $this->logger->logUpdate($watchFile, $user, $changes);

        $this->assertSame($watchFile, $activity->getWatchFile());
        $this->assertSame($user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('changes', $actionData);
        $this->assertEquals([
            'old' => [],
            'new' => [],
        ], $actionData['changes']);
    }

    public function testLogSourceStatusChange(): void
    {
        $user = $this->createStub(User::class);
        $watchFile = $this->createStub(WatchFile::class);
        $source = $this->createStub(Source::class);

        $source->method('getName')
->willReturn('Test Source');
        $source->method('getId')
->willReturn('source-id');
        $source->method('getType')
->willReturn(SourceType::WEBSITE);
        $source->method('getUrl')
->willReturn('https://example.com');

        $activity = $this->logger->logSourceStatusChange(
            $source,
            $watchFile,
            $user,
            SourceStatus::ACTIVE,
            SourceStatus::INACTIVE
        );

        $this->assertSame($watchFile, $activity->getWatchFile());
        $this->assertSame($user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::SOURCE_STATUS_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('source_name', $actionData);
        $this->assertEquals('Test Source', $actionData['source_name']);
        $this->assertArrayHasKey('source_id', $actionData);
        $this->assertEquals('source-id', $actionData['source_id']);
        $this->assertArrayHasKey('source_type', $actionData);
        $this->assertEquals('website', $actionData['source_type']);
        $this->assertArrayHasKey('source_url', $actionData);
        $this->assertEquals('https://example.com', $actionData['source_url']);
        $this->assertArrayHasKey('status', $actionData);
        $this->assertEquals('active', $actionData['status']);
    }

    public function testLogSourceStatusChangeWithDisabledStatus(): void
    {
        $user = $this->createStub(User::class);
        $watchFile = $this->createStub(WatchFile::class);
        $source = $this->createStub(Source::class);

        $source->method('getName')
->willReturn('Test Source');
        $source->method('getId')
->willReturn('source-id');
        $source->method('getType')
->willReturn(SourceType::WEBSITE);
        $source->method('getUrl')
->willReturn('https://example.com');

        $activity = $this->logger->logSourceStatusChange(
            $source,
            $watchFile,
            $user,
            SourceStatus::INACTIVE,
            SourceStatus::ACTIVE
        );

        $this->assertSame($watchFile, $activity->getWatchFile());
        $this->assertSame($user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::SOURCE_STATUS_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('status', $actionData);
        $this->assertEquals('inactive', $actionData['status']);
    }
}
