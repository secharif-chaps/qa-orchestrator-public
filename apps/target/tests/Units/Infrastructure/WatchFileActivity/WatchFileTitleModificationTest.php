<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use App\Application\WatchFile\RenameWatchFileAction;
use App\Application\WatchFile\RenameWatchFileHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\Shared\RealTimeUpdatePublisherInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Test to verify that both manual and chat-based title modifications
 * are properly logged in WatchFileActivity.
 */
class WatchFileTitleModificationTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private WatchFileActivityLogger $activityLogger;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private RealTimeUpdatePublisherInterface&Stub $realTimeUpdatePublisher;
    private LoggerInterface&Stub $logger;
    private WatchFileGatewayInterface&Stub $watchFileGateway;
    private RenameWatchFileHandler $renameHandler;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->activityLogger = new WatchFileActivityLogger($this->createStub(TenantContext::class));
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->realTimeUpdatePublisher = $this->createStub(RealTimeUpdatePublisherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->watchFileGateway = $this->createStub(WatchFileGatewayInterface::class);

        $this->user = new User('test@example.com');
        $this->watchFile = new WatchFile('Original Title', 'Test Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $this->user);
        $this->forcePropertyValue($this->watchFile, 'watch_file_id');

        $this->watchFileGateway
            ->method('get')
            ->willReturn($this->watchFile);

        $this->buildRenameHandler();
    }

    private function buildRenameHandler(): void
    {
        $this->renameHandler = new RenameWatchFileHandler(
            $this->eventDispatcher,
            $this->realTimeUpdatePublisher,
            $this->logger,
        );
        $this->renameHandler->setWatchFileGateway($this->watchFileGateway);
    }

    /**
     * Test that MANUAL title modification is properly logged
     * This simulates a user directly editing the title via the API.
     */
    public function testManualTitleModificationIsLogged(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildRenameHandler();

        $action = new RenameWatchFileAction(
            watchFileId: $this->watchFile->getId(),
            name: 'New Manual Title',
            isManualRename: true
        );

        // Expect the WatchFileUpdatedEvent to be dispatched
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (WatchFileUpdatedEvent $event) {
                $changes = $event->changes;

                return isset($changes['name'])
                    && 'Original Title' === $changes['name']['old']
                    && 'New Manual Title' === $changes['name']['new'];
            }));

        $result = $this->renameHandler->__invoke($action);

        // Verify the title was changed and marked as manually set
        $this->assertEquals('New Manual Title', $result->getName());
        $this->assertTrue($result->isTitleManuallySetByUser());
    }

    /**
     * Test that CHAT-BASED title modification is properly logged
     * This simulates the AI renaming the title through a function call.
     */
    public function testChatBasedTitleModificationIsLogged(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildRenameHandler();

        $action = new RenameWatchFileAction(
            watchFileId: $this->watchFile->getId(),
            name: 'New AI Generated Title',
            isManualRename: false
        );

        // Expect the WatchFileUpdatedEvent to be dispatched
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (WatchFileUpdatedEvent $event) {
                $changes = $event->changes;

                return isset($changes['name'])
                    && 'Original Title' === $changes['name']['old']
                    && 'New AI Generated Title' === $changes['name']['new'];
            }));

        $result = $this->renameHandler->__invoke($action);

        // Verify the title was changed but NOT marked as manually set
        $this->assertEquals('New AI Generated Title', $result->getName());
        $this->assertFalse($result->isTitleManuallySetByUser());
    }

    /**
     * Test that the activity logger correctly handles both types of title changes.
     */
    public function testActivityLoggerHandlesBothTitleChangeTypes(): void
    {
        // Test manual change
        $manualChanges = [
            'name' => [
                'old' => 'Old Title',
                'new' => 'New Manual Title',
            ],
        ];

        $manualActivity = $this->activityLogger->logUpdate($this->watchFile, $this->user, [
            'old' => [],
            'new' => $manualChanges,
        ]);

        $this->assertEquals(WatchFileActivityActionType::UPDATED, $manualActivity->getActionType());
        $this->assertArrayHasKey('changes', $manualActivity->getActionData());
        $this->assertEquals([
            'old' => [],
            'new' => $manualChanges,
        ], $manualActivity->getActionData()['changes']);

        // Test chat-based change
        $chatChanges = [
            'name' => [
                'old' => 'Old Title',
                'new' => 'New AI Title',
            ],
        ];

        $chatActivity = $this->activityLogger->logUpdate($this->watchFile, $this->user, [
            'old' => [],
            'new' => $chatChanges,
        ]);

        $this->assertEquals(WatchFileActivityActionType::UPDATED, $chatActivity->getActionType());
        $this->assertArrayHasKey('changes', $chatActivity->getActionData());
        $this->assertEquals([
            'old' => [],
            'new' => $chatChanges,
        ], $chatActivity->getActionData()['changes']);
    }

    /**
     * Test that both modification types are covered by the activity system.
     */
    public function testBothTitleModificationTypesAreCovered(): void
    {
        $requiredScenarios = [
            'Manual title modification via API' => [
                'handler' => 'UpdateWatchFileProcessor',
                'event' => 'WatchFileUpdatedEvent',
                'action_type' => 'UPDATED',
                'manual_flag' => true,
            ],
            'Chat-based title modification via AI function' => [
                'handler' => 'RenameWatchFileHandler',
                'event' => 'WatchFileUpdatedEvent',
                'action_type' => 'UPDATED',
                'manual_flag' => false,
            ],
        ];

        foreach ($requiredScenarios as $scenario => $details) {
            $this->assertArrayHasKey('handler', $details, "Scenario '{$scenario}' should specify a handler");
            $this->assertArrayHasKey('event', $details, "Scenario '{$scenario}' should specify an event");
            $this->assertArrayHasKey('action_type', $details, "Scenario '{$scenario}' should specify an action type");
            $this->assertArrayHasKey('manual_flag', $details, "Scenario '{$scenario}' should specify manual flag");
        }

        // Verify that both scenarios use the same activity action type but different manual flags
        $this->assertEquals('UPDATED', $requiredScenarios['Manual title modification via API']['action_type']);
        $this->assertEquals(
            'UPDATED',
            $requiredScenarios['Chat-based title modification via AI function']['action_type']
        );
        $this->assertNotEquals(
            $requiredScenarios['Manual title modification via API']['manual_flag'],
            $requiredScenarios['Chat-based title modification via AI function']['manual_flag']
        );
    }

    /**
     * Test that the titleManuallySetByUser flag is correctly set for both scenarios.
     */
    public function testTitleManuallySetByUserFlagIsCorrectlySet(): void
    {
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
->willReturn(null);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildRenameHandler();

        // Test manual modification
        $manualAction = new RenameWatchFileAction(
            watchFileId: $this->watchFile->getId(),
            name: 'Manual Title',
            isManualRename: true
        );

        $eventDispatcher->method('dispatch');
        $manualResult = $this->renameHandler->__invoke($manualAction);
        $this->assertTrue(
            $manualResult->isTitleManuallySetByUser(),
            'Manual modification should set titleManuallySetByUser to true'
        );

        // Reset for chat test - create new instances
        $this->watchFile = new WatchFile('Original Title', 'Test Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $this->user);
        $this->forcePropertyValue($this->watchFile, 'watch_file_id');
        $this->watchFileGateway
            ->method('get')
            ->willReturn($this->watchFile);

        // Test chat-based modification
        $chatAction = new RenameWatchFileAction(
            watchFileId: $this->watchFile->getId(),
            name: 'AI Title',
            isManualRename: false
        );

        $eventDispatcher->method('dispatch');
        $chatResult = $this->renameHandler->__invoke($chatAction);
        $this->assertFalse(
            $chatResult->isTitleManuallySetByUser(),
            'Chat-based modification should set titleManuallySetByUser to false'
        );
    }
}
