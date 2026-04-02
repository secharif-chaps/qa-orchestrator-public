<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFile;

use App\Application\WatchFile\RenameWatchFileAction;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Integration tests for RenameWatchFileHandler via MessageBus.
 *
 * Note: RenameWatchFileAction routes to async_priority_low transport,
 * so messages are processed asynchronously (queued).
 *
 * These tests verify the complete flow from N8N workflow:
 * 1. Dispatch action to MessageBus
 * 2. Message is queued in async_priority_low transport
 * 3. Process the queue
 * 4. Database state is correct after processing
 */
class RenameWatchFileHandlerTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
            ->get(EntityManagerInterface::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    public function testRenameWatchFileSuccess(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newName = 'Renamed WatchFile - Competitive Analysis 2025';

        $action = new RenameWatchFileAction(watchFileId: $watchFileId, name: $newName, isManualRename: false);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(RenameWatchFileAction::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $this->assertEquals($newName, $updatedWatchFile->getName());
        $this->assertFalse($updatedWatchFile->isTitleManuallySetByUser());
    }

    public function testRenameWatchFileWithManualFlag(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newName = 'User-Defined Custom Name';

        $action = new RenameWatchFileAction(watchFileId: $watchFileId, name: $newName, isManualRename: true);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $this->assertEquals($newName, $updatedWatchFile->getName());
        $this->assertTrue($updatedWatchFile->isTitleManuallySetByUser());
    }

    public function testRenameWatchFileMultipleTimes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $names = ['First Rename', 'Second Rename', 'Final Rename - Strategic Analysis'];

        foreach ($names as $name) {
            $action = new RenameWatchFileAction(watchFileId: $watchFileId, name: $name, isManualRename: false);
            $this->bus()
                ->dispatch($action);
        }

        // Process all queued messages
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        // Should have the last name after all renames
        $this->assertEquals('Final Rename - Strategic Analysis', $updatedWatchFile->getName());
    }

    public function testRenameWatchFileWithUnicodeContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $newName = 'Analyse Concurrentielle 2025 🎯 - Marchés Européens €£¥';

        $action = new RenameWatchFileAction(watchFileId: $watchFileId, name: $newName, isManualRename: true);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $this->assertEquals($newName, $updatedWatchFile->getName());
        $this->assertStringContainsString('🎯', $updatedWatchFile->getName());
        $this->assertStringContainsString('€£¥', $updatedWatchFile->getName());
    }

    public function testRenameWatchFileWithLongName(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $longName = 'Comprehensive Market Analysis and Competitive Intelligence Report for Q1 2025 '
            . 'Including Regulatory Changes, Emerging Technology Trends, and Strategic Business Implications '
            . 'Across European, North American, and Asia-Pacific Regions';

        $action = new RenameWatchFileAction(watchFileId: $watchFileId, name: $longName, isManualRename: false);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        $this->assertEquals($longName, $updatedWatchFile->getName());
    }

    public function testRenameWatchFilePreservesOtherAttributes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Store original attributes
        $originalCreatedAt = $watchFile->getCreatedAt();
        $originalStatus = $watchFile->getStatus();
        $originalCreatedBy = $watchFile->getCreatedBy();
        $this->assertNotNull($originalCreatedBy);
        $originalCreatedById = $originalCreatedBy->getId();

        $action = new RenameWatchFileAction(
            watchFileId: $watchFileId,
            name: 'New Name After Rename',
            isManualRename: true
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);

        // Name changed
        $this->assertEquals('New Name After Rename', $updatedWatchFile->getName());

        // Other attributes preserved (use format to compare dates without microsecond precision)
        $this->assertEquals(
            $originalCreatedAt->format('Y-m-d H:i:s'),
            $updatedWatchFile->getCreatedAt()
                ->format('Y-m-d H:i:s')
        );
        $this->assertEquals($originalStatus, $updatedWatchFile->getStatus());
        $updatedCreatedBy = $updatedWatchFile->getCreatedBy();
        $this->assertNotNull($updatedCreatedBy);
        $this->assertEquals($originalCreatedById, $updatedCreatedBy->getId());
    }

    public function testRenameWatchFileWithNonExistentIdFails(): void
    {
        $action = new RenameWatchFileAction(
            watchFileId: '00000000-0000-0000-0000-000000000000',
            name: 'New Name',
            isManualRename: false
        );

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertCount(1);

        $this->expectException(\Exception::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
    }

    public function testRenameWatchFileWithEmptyIdFails(): void
    {
        $action = new RenameWatchFileAction(watchFileId: '', name: 'New Name', isManualRename: false);

        $this->bus()
            ->dispatch($action);
        $this->transport('async_priority_low')
            ->queue()
            ->assertCount(1);

        $this->expectException(\Exception::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
    }

    public function testRenameWatchFileMessagesAreQueued(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $action = new RenameWatchFileAction(
            watchFileId: $watchFileId,
            name: 'Queued Rename Test',
            isManualRename: false
        );

        $this->bus()
            ->dispatch($action);

        // Before processing, queue should have the action
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(RenameWatchFileAction::class);

        // Name should not have changed yet (message not processed)
        $this->entityManager->clear();
        $unchangedWatchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($unchangedWatchFile);
        $this->assertEquals('Original Name', $unchangedWatchFile->getName());

        // Process the queue
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();

        // After processing, queue should be empty
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        // Now name should have changed
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $this->assertEquals('Queued Rename Test', $updatedWatchFile->getName());
    }
}
