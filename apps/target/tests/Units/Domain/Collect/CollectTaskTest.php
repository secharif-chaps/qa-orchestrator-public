<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Collect;

use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CollectTaskTest extends TestCase
{
    private CollectTask $collectTask;
    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );

        $this->collectTask = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'bakus',
            configuration: [
                'test' => 'config',
            ]
        );
    }

    public function testCollectTaskInitialState(): void
    {
        $this->assertEquals('bakus', $this->collectTask->getProviderName());
        $this->assertEquals([
            'test' => 'config',
        ], $this->collectTask->getConfiguration());
        $this->assertEquals(CollectTaskStatus::CREATED, $this->collectTask->getStatus());
        $this->assertNull($this->collectTask->getProviderTaskId());
        // No result field exists
        $this->assertNull($this->collectTask->getCompletedAt());
        $this->assertTrue($this->collectTask->isActive()); // CREATED is in ACTIVE_STATUSES
        $this->assertFalse($this->collectTask->isCompleted());
        $this->assertFalse($this->collectTask->isFailed());
        $this->assertFalse($this->collectTask->isCancelled());
    }

    public function testStartCollectTask(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);

        $this->assertEquals(CollectTaskStatus::QUEUED, $this->collectTask->getStatus());
        $this->assertEquals('provider-task-123', $this->collectTask->getProviderTaskId());
        $this->assertTrue($this->collectTask->isActive());
    }

    public function testResumeCollectTask(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);
        $this->collectTask->resume($this->eventDispatcher);

        $this->assertEquals(CollectTaskStatus::RUNNING, $this->collectTask->getStatus());
        $this->assertTrue($this->collectTask->isActive());
    }

    public function testCancelFromRunningState(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);
        $this->collectTask->resume($this->eventDispatcher);
        $this->collectTask->cancel();

        $this->assertEquals(CollectTaskStatus::CANCELLED, $this->collectTask->getStatus());
        $this->assertTrue($this->collectTask->isCancelled());
        $this->assertFalse($this->collectTask->isActive());
        $this->assertNotNull($this->collectTask->getCompletedAt());
    }

    public function testCompleteCollectTask(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);
        $this->collectTask->resume($this->eventDispatcher);
        $this->collectTask->complete($this->eventDispatcher);

        $this->assertEquals(CollectTaskStatus::COMPLETED, $this->collectTask->getStatus());
        $this->assertTrue($this->collectTask->isCompleted());
        $this->assertFalse($this->collectTask->isActive());
        $this->assertNotNull($this->collectTask->getCompletedAt());
    }

    public function testFailCollectTask(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);
        $this->collectTask->resume($this->eventDispatcher);
        $this->collectTask->fail($this->eventDispatcher);

        $this->assertEquals(CollectTaskStatus::FAILED, $this->collectTask->getStatus());
        $this->assertTrue($this->collectTask->isFailed());
        $this->assertFalse($this->collectTask->isActive());
        $this->assertNotNull($this->collectTask->getCompletedAt());
    }

    public function testCancelCollectTask(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);
        $this->collectTask->cancel();

        $this->assertEquals(CollectTaskStatus::CANCELLED, $this->collectTask->getStatus());
        $this->assertTrue($this->collectTask->isCancelled());
        $this->assertFalse($this->collectTask->isActive());
        $this->assertNotNull($this->collectTask->getCompletedAt());
    }

    public function testInvalidTransitionThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot transition from created to completed');

        $this->collectTask->complete($this->eventDispatcher);
    }

    public function testUpdateStatus(): void
    {
        $this->collectTask->start('provider-task-123', $this->eventDispatcher);
        $this->collectTask->updateStatus(CollectTaskStatus::RUNNING);

        $this->assertEquals(CollectTaskStatus::RUNNING, $this->collectTask->getStatus());
    }
}
