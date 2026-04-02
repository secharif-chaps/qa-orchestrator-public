<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Application\WatchFile\Source\BatchChangeSourceStatusAction;
use App\Application\WatchFile\Source\BatchChangeSourceStatusHandler;
use App\Application\WatchFile\Source\ChangeSourceStatusAction;
use App\Application\WatchFile\Source\ChangeSourceStatusHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use App\UserInterface\Dto\Source\BatchChangeSourceDataDto;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class BatchChangeSourceStatusHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullSourceGateway $sourceGateway;
    private Security&Stub $security;
    private NullMessageBus $messageBus;
    private EventDispatcherInterface&Stub $eventDispatcher;
    private LoggerInterface&Stub $logger;
    private BatchChangeSourceStatusHandler $handler;
    private ChangeSourceStatusHandler $changeSourceStatusHandler;
    private User $user;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->security = $this->createStub(Security::class);
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->user = new User('user-id', 'user@example.com');
        $this->security
            ->method('getUser')
            ->willReturn($this->user);

        $this->buildHandlers();
    }

    private function buildHandlers(): void
    {
        $this->changeSourceStatusHandler = new ChangeSourceStatusHandler(
            new NullMessageBus(),
            $this->eventDispatcher,
            $this->logger
        );
        $this->changeSourceStatusHandler->setWatchFileGateway($this->watchFileGateway);
        $this->changeSourceStatusHandler->setSourceGateway($this->sourceGateway);

        $this->messageBus = new NullMessageBus(function ($message) {
            if ($message instanceof ChangeSourceStatusAction) {
                return $this->changeSourceStatusHandler->__invoke($message);
            }

            return null;
        });

        $this->handler = new BatchChangeSourceStatusHandler($this->security, $this->messageBus, $this->logger);
        $this->handler->setWatchFileGateway($this->watchFileGateway);
        $this->handler->setSourceGateway($this->sourceGateway);
    }

    public function testInvokeWithValidSources(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $source1 = $this->createSource('source-1', $watchFile, SourceStatus::INACTIVE);
        $source2 = $this->createSource('source-2', $watchFile, SourceStatus::INACTIVE);
        $source3 = $this->createSource('source-3', $watchFile, SourceStatus::INACTIVE);

        $action = new BatchChangeSourceStatusAction(
            sources: [
                new BatchChangeSourceDataDto('source-1', 'watch-file-id'),
                new BatchChangeSourceDataDto('source-2', 'watch-file-id'),
                new BatchChangeSourceDataDto('source-3', 'watch-file-id'),
            ],
            status: SourceStatus::ACTIVE
        );

        $eventDispatcher->expects($this->exactly(3))
            ->method('dispatch')
            ->with($this->isInstanceOf(\App\Domain\Source\SourceStatusChangedEvent::class));

        $result = $this->handler->__invoke($action);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('failed', $result);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 3 source(s) processed.', $result['message']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['results']);
        $this->assertCount(0, $result['errors']);

        foreach ($result['results'] as $i => $sourceResult) {
            $this->assertEquals('source-' . ($i + 1), $sourceResult['id']);
            $this->assertEquals('watch-file-id', $sourceResult['watchFileId']);
            $this->assertEquals('active', $sourceResult['status']);
            $this->assertTrue($sourceResult['success']);
        }
    }

    public function testInvokeWithSourcesAlreadyHavingTargetStatus(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $source1 = $this->createSource('source-1', $watchFile, SourceStatus::ACTIVE);
        $source2 = $this->createSource('source-2', $watchFile, SourceStatus::INACTIVE);

        $action = new BatchChangeSourceStatusAction(
            sources: [
                new BatchChangeSourceDataDto('source-1', 'watch-file-id'),
                new BatchChangeSourceDataDto('source-2', 'watch-file-id'),
            ],
            status: SourceStatus::ACTIVE
        );

        // Only source-2 should trigger an event (source-1 already has ACTIVE status)
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($source2) {
                return $event instanceof \App\Domain\Source\SourceStatusChangedEvent
                    && $event->source->getId() === $source2->getId();
            }));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testInvokeWithPartialFailures(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $source1 = $this->createSource('source-1', $watchFile, SourceStatus::INACTIVE);
        // source-2 doesn't exist

        $action = new BatchChangeSourceStatusAction(
            sources: [
                new BatchChangeSourceDataDto('source-1', 'watch-file-id'),
                new BatchChangeSourceDataDto('source-2', 'watch-file-id'),
            ],
            status: SourceStatus::ACTIVE
        );

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('partial success', $result['message']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertCount(1, $result['errors']);

        $this->assertEquals('source-2', $result['errors'][0]['id']);
        $this->assertIsString($result['errors'][0]['error']);
        $this->assertStringContainsString('Unable to find sources', (string) $result['errors'][0]['error']);
    }

    public function testInvokeWithEmptySources(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $action = new BatchChangeSourceStatusAction(sources: [], status: SourceStatus::ACTIVE);

        $eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed: No sources to process.', $result['message']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['failed']);
    }

    public function testInvokeWithEmptyStringValues(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $action = new BatchChangeSourceStatusAction(
            sources: [
                new BatchChangeSourceDataDto('', 'watch-file-id'),
                new BatchChangeSourceDataDto('source-1', ''),
            ],
            status: SourceStatus::ACTIVE
        );

        $eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertEquals('Batch operation failed: All 2 source(s) failed to process.', $result['message']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(2, $result['failed']);
        $this->assertCount(2, $result['errors']);

        foreach ($result['errors'] as $error) {
            $this->assertIsString($error['error']);
            $this->assertStringContainsString('Missing or empty required source data', (string) $error['error']);
        }
    }

    public function testInvokeWithDifferentWatchFileIds(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $action = new BatchChangeSourceStatusAction(
            sources: [
                new BatchChangeSourceDataDto('source-1', 'watch-file-1'),
                new BatchChangeSourceDataDto('source-2', 'watch-file-2'),
            ],
            status: SourceStatus::ACTIVE
        );

        $eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(2, $result['failed']);
        $this->assertCount(2, $result['errors']);

        $this->assertIsString($result['errors'][1]['error']);
        $this->assertStringContainsString(
            'All sources must belong to the same watch file',
            (string) $result['errors'][1]['error']
        );
    }

    public function testInvokeWithWatchFileActive(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $source1 = $this->createSource('source-1', $watchFile, SourceStatus::INACTIVE);

        $action = new BatchChangeSourceStatusAction(
            sources: [new BatchChangeSourceDataDto('source-1', 'watch-file-id')],
            status: SourceStatus::ACTIVE
        );

        $eventDispatcher->expects($this->never())
            ->method('dispatch');

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['errors']);
        $this->assertIsString($result['errors'][0]['error']);
        $this->assertStringContainsString('active status', (string) $result['errors'][0]['error']);
    }

    public function testInvokeWithDifferentStatus(): void
    {
        $eventDispatcher = $this->createMockWithExpectations(EventDispatcherInterface::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->buildHandlers();

        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch-file-id');
        $this->watchFileGateway->save($watchFile);

        $source1 = $this->createSource('source-1', $watchFile, SourceStatus::ACTIVE);

        $action = new BatchChangeSourceStatusAction(
            sources: [new BatchChangeSourceDataDto('source-1', 'watch-file-id')],
            status: SourceStatus::INACTIVE
        );

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof \App\Domain\Source\SourceStatusChangedEvent
                    && SourceStatus::INACTIVE === $event->status;
            }));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals('inactive', $result['results'][0]['status']);
    }

    private function createSource(string $id, WatchFile $watchFile, SourceStatus $status): Source
    {
        $source = new Source(
            'Test Source ' . $id,
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com/' . $id,
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, $id);
        $source->setStatus($status);
        $this->sourceGateway->save($source);

        return $source;
    }
}
