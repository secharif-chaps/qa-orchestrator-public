<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Mercure;

use App\Domain\Organisation\Organisation;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Mercure\WatchFileMercureDoctrineListener;
use App\Tests\Utils\EntityUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(WatchFileMercureDoctrineListener::class)]
class WatchFileMercureDoctrineListenerTest extends TestCase
{
    use EntityUtilsTrait;
    private RealTimeUpdatePublisherInterface&MockObject $publisher;
    private WatchFileMercureDoctrineListener $listener;
    private EntityManagerInterface&Stub $entityManager;

    protected function setUp(): void
    {
        $this->publisher = $this->createMock(RealTimeUpdatePublisherInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->listener = new WatchFileMercureDoctrineListener($this->publisher);
    }

    public function testPostUpdateTracksWatchFileForPublishing(): void
    {
        $watchFile = $this->createWatchFile('wf-1');

        $this->publisher
            ->expects($this->never())
            ->method('publishWatchFileUpdate');

        $event = new PostUpdateEventArgs($watchFile, $this->entityManager);
        $this->listener->postUpdate($event);

        // No publish yet - should happen on postFlush
    }

    public function testPostFlushPublishesTrackedWatchFiles(): void
    {
        $watchFile = $this->createWatchFile('wf-1');

        $this->publisher
            ->expects($this->once())
            ->method('publishWatchFileUpdate')
            ->with($watchFile);

        $updateEvent = new PostUpdateEventArgs($watchFile, $this->entityManager);
        $this->listener->postUpdate($updateEvent);

        $flushEvent = new PostFlushEventArgs($this->entityManager);
        $this->listener->postFlush($flushEvent);
    }

    public function testPostFlushDoesNotPublishWithoutPendingUpdates(): void
    {
        $this->publisher
            ->expects($this->never())
            ->method('publishWatchFileUpdate');

        $flushEvent = new PostFlushEventArgs($this->entityManager);
        $this->listener->postFlush($flushEvent);
    }

    public function testPostFlushClearsPendingUpdates(): void
    {
        $watchFile = $this->createWatchFile('wf-1');

        $this->publisher
            ->expects($this->once())
            ->method('publishWatchFileUpdate');

        $updateEvent = new PostUpdateEventArgs($watchFile, $this->entityManager);
        $this->listener->postUpdate($updateEvent);

        $flushEvent = new PostFlushEventArgs($this->entityManager);
        $this->listener->postFlush($flushEvent);

        // Second flush should not trigger any publish
        $this->listener->postFlush($flushEvent);
    }

    public function testMultipleWatchFilesArePublished(): void
    {
        $watchFile1 = $this->createWatchFile('wf-1');
        $watchFile2 = $this->createWatchFile('wf-2');

        $publishedFiles = [];
        $this->publisher
            ->expects($this->exactly(2))
            ->method('publishWatchFileUpdate')
            ->willReturnCallback(function (WatchFile $wf) use (&$publishedFiles) {
                $publishedFiles[] = $wf->getId();
            });

        $this->listener->postUpdate(new PostUpdateEventArgs($watchFile1, $this->entityManager));
        $this->listener->postUpdate(new PostUpdateEventArgs($watchFile2, $this->entityManager));

        $flushEvent = new PostFlushEventArgs($this->entityManager);
        $this->listener->postFlush($flushEvent);

        $this->assertContains('wf-1', $publishedFiles);
        $this->assertContains('wf-2', $publishedFiles);
    }

    public function testSameWatchFileUpdatedMultipleTimesPublishesOnce(): void
    {
        $watchFile = $this->createWatchFile('wf-1');

        $this->publisher
            ->expects($this->once())
            ->method('publishWatchFileUpdate')
            ->with($watchFile);

        // Same WatchFile updated twice in same flush cycle
        $this->listener->postUpdate(new PostUpdateEventArgs($watchFile, $this->entityManager));
        $this->listener->postUpdate(new PostUpdateEventArgs($watchFile, $this->entityManager));

        $flushEvent = new PostFlushEventArgs($this->entityManager);
        $this->listener->postFlush($flushEvent);
    }

    public function testIgnoresNonWatchFileEntities(): void
    {
        $user = $this->createStub(User::class);

        $this->publisher
            ->expects($this->never())
            ->method('publishWatchFileUpdate');

        $event = new PostUpdateEventArgs($user, $this->entityManager);
        $this->listener->postUpdate($event);

        $flushEvent = new PostFlushEventArgs($this->entityManager);
        $this->listener->postFlush($flushEvent);
    }

    private function createWatchFile(string $id): WatchFile
    {
        $watchFile = new WatchFile(
            'Test WatchFile',
            'A watchfile for testing', new Organisation('Test Org', 'test-org-id'),
            new User(Uuid::v4()->toRfc4122()),
        );

        $this->forcePropertyValue($watchFile, $id);

        return $watchFile;
    }
}
