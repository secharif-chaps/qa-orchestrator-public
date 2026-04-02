<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Actor;

use App\Application\Actor\BatchChangeActorStatusAction;
use App\Application\Actor\BatchChangeActorStatusHandler;
use App\Application\Actor\ChangeActorStatusAction;
use App\Application\Actor\ChangeActorStatusHandler;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\User\User;
use App\Tests\Utils\MockHelpersTrait;
use App\UserInterface\Dto\Actor\BatchChangeActorDataDto;
use App\UserInterface\Dto\Actor\ChangeActorStatusOutputDto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class BatchChangeActorStatusHandlerTest extends TestCase
{
    use MockHelpersTrait;
    private ChangeActorStatusHandler&MockObject $changeActorStatusHandler;
    private Security&Stub $security;
    private BatchChangeActorStatusHandler $handler;
    private User $user;

    protected function setUp(): void
    {
        $this->changeActorStatusHandler = $this->createMockWithExpectations(ChangeActorStatusHandler::class);
        $this->security = $this->createStub(Security::class);
        $this->user = $this->createStub(User::class);

        $this->security
            ->method('getUser')
            ->willReturn($this->user);

        $this->handler = new BatchChangeActorStatusHandler($this->changeActorStatusHandler, $this->security);
    }

    public function testInvokeWithValidActors(): void
    {
        $actors = [
            new BatchChangeActorDataDto(id: 'actor-1', sourceIds: [], watchFileId: 'watch-file-1'),
            new BatchChangeActorDataDto(id: 'actor-2', sourceIds: [], watchFileId: 'watch-file-1'),
            new BatchChangeActorDataDto(id: 'actor-3', sourceIds: [], watchFileId: 'watch-file-1'),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->exactly(3))
            ->method('__invoke')
            ->willReturnCallback(function (ChangeActorStatusAction $changeAction) {
                $mockActor = $this->createStub(Actor::class);

                return new ChangeActorStatusOutputDto(actor: $mockActor, sources: []);
            });

        $result = $this->handler->__invoke($action);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('failed', $result);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 3 actor(s) processed.', $result['message']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['results']);
        $this->assertCount(0, $result['errors']);

        foreach ($result['results'] as $i => $actorResult) {
            $this->assertEquals('actor-' . ($i + 1), $actorResult['id']);
            $this->assertEquals('watch-file-1', $actorResult['watchFileId']);
            $this->assertEquals('active', $actorResult['status']);
            $this->assertTrue($actorResult['success']);
        }
    }

    public function testInvokeWithPartialFailures(): void
    {
        $actors = [
            new BatchChangeActorDataDto(id: 'actor-1', sourceIds: [], watchFileId: 'watch-file-1'),
            new BatchChangeActorDataDto(id: 'actor-2', sourceIds: [], watchFileId: 'watch-file-1'),
            new BatchChangeActorDataDto(id: 'actor-3', sourceIds: [], watchFileId: 'watch-file-1'),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->exactly(3))
            ->method('__invoke')
            ->willReturnCallback(function (ChangeActorStatusAction $changeAction) {
                $mockActor = $this->createStub(Actor::class);

                if ('actor-2' === $changeAction->actorId) {
                    throw new \Exception('Actor not found');
                }

                return new ChangeActorStatusOutputDto(actor: $mockActor, sources: []);
            });

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('partial success', $result['message']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(2, $result['processed']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(2, $result['results']);
        $this->assertCount(1, $result['errors']);

        $this->assertEquals('actor-2', $result['errors'][0]['id']);
        $this->assertEquals('Actor not found', $result['errors'][0]['error']);
    }

    public function testInvokeWithEmptyActors(): void
    {
        $action = new BatchChangeActorStatusAction([]);

        $this->changeActorStatusHandler
            ->expects($this->never())
            ->method('__invoke');

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed: No actors to process.', $result['message']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(0, $result['results']);
        $this->assertCount(0, $result['errors']);
    }

    public function testInvokeCreatesCorrectChangeActorStatusAction(): void
    {
        $actors = [
            new BatchChangeActorDataDto(
                id: 'test-actor',
                sourceIds: ['source-1', 'source-2'],
                watchFileId: 'test-watch-file',
            ),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (ChangeActorStatusAction $changeAction) {
                return 'test-actor' === $changeAction->actorId
                    && 'test-watch-file' === $changeAction->watchFileId
                    && ['source-1', 'source-2'] === $changeAction->sourceIds
                    && ActorStatus::ACTIVE === $changeAction->newStatus
                    && $changeAction->user === $this->user;
            }))
            ->willReturn(new ChangeActorStatusOutputDto(actor: $this->createStub(Actor::class), sources: []));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 1 actor(s) processed.', $result['message']);
    }

    public function testInvokeWithEmptyStringValues(): void
    {
        $actors = [
            new BatchChangeActorDataDto(id: '', sourceIds: [], watchFileId: 'valid-watch-file-id'),
            new BatchChangeActorDataDto(id: 'valid-actor-id', sourceIds: [], watchFileId: ''),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->never())
            ->method('__invoke');

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertEquals('Batch operation failed: All 2 actor(s) failed to process.', $result['message']);
        $this->assertEquals(2, $result['total']);
        $this->assertEquals(0, $result['processed']);
        $this->assertEquals(2, $result['failed']);
        $this->assertCount(2, $result['errors']);

        foreach ($result['errors'] as $error) {
            $this->assertIsString($error['error']);
            $this->assertStringContainsString('Missing or empty required actor data', $error['error']);
        }
    }

    public function testInvokeWithNoSourceIds(): void
    {
        $actors = [new BatchChangeActorDataDto(id: 'actor-1', sourceIds: [], watchFileId: 'watch-file-1')];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (ChangeActorStatusAction $changeAction) {
                return 'actor-1' === $changeAction->actorId
                    && 'watch-file-1' === $changeAction->watchFileId
                    && [] === $changeAction->sourceIds
                    && ActorStatus::ACTIVE === $changeAction->newStatus
                    && $changeAction->user === $this->user;
            }))
            ->willReturn(new ChangeActorStatusOutputDto(actor: $this->createStub(Actor::class), sources: []));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 1 actor(s) processed.', $result['message']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertCount(0, $result['errors']);
    }

    public function testInvokeWithSingleSourceId(): void
    {
        $actors = [
            new BatchChangeActorDataDto(id: 'actor-1', sourceIds: ['source-123'], watchFileId: 'watch-file-1'),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (ChangeActorStatusAction $changeAction) {
                return 'actor-1' === $changeAction->actorId
                    && 'watch-file-1' === $changeAction->watchFileId
                    && ['source-123'] === $changeAction->sourceIds
                    && ActorStatus::ACTIVE === $changeAction->newStatus
                    && $changeAction->user === $this->user;
            }))
            ->willReturn(new ChangeActorStatusOutputDto(actor: $this->createStub(Actor::class), sources: []));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 1 actor(s) processed.', $result['message']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertCount(0, $result['errors']);
    }

    public function testInvokeWithMultipleSourceIds(): void
    {
        $actors = [
            new BatchChangeActorDataDto(
                id: 'actor-1',
                sourceIds: ['source-123', 'source-456', 'source-789'],
                watchFileId: 'watch-file-1',
            ),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (ChangeActorStatusAction $changeAction) {
                return 'actor-1' === $changeAction->actorId
                    && 'watch-file-1' === $changeAction->watchFileId
                    && ['source-123', 'source-456', 'source-789'] === $changeAction->sourceIds
                    && ActorStatus::ACTIVE === $changeAction->newStatus
                    && $changeAction->user === $this->user;
            }))
            ->willReturn(new ChangeActorStatusOutputDto(actor: $this->createStub(Actor::class), sources: []));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 1 actor(s) processed.', $result['message']);
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertCount(0, $result['errors']);
    }

    public function testInvokeWithMixedSourceIdsScenarios(): void
    {
        $actors = [
            new BatchChangeActorDataDto(id: 'actor-no-sources', sourceIds: [], watchFileId: 'watch-file-1'),
            new BatchChangeActorDataDto(
                id: 'actor-single-source',
                sourceIds: ['source-abc'],
                watchFileId: 'watch-file-1',
            ),
            new BatchChangeActorDataDto(
                id: 'actor-multiple-sources',
                sourceIds: ['source-def', 'source-ghi', 'source-jkl'],
                watchFileId: 'watch-file-1',
            ),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $callCount = 0;
        $this->changeActorStatusHandler
            ->expects($this->exactly(3))
            ->method('__invoke')
            ->willReturnCallback(function (ChangeActorStatusAction $changeAction) use (&$callCount) {
                ++$callCount;
                $mockActor = $this->createStub(Actor::class);

                switch ($callCount) {
                    case 1:
                        $this->assertEquals('actor-no-sources', $changeAction->actorId);
                        $this->assertEquals([], $changeAction->sourceIds);
                        break;
                    case 2:
                        $this->assertEquals('actor-single-source', $changeAction->actorId);
                        $this->assertEquals(['source-abc'], $changeAction->sourceIds);
                        break;
                    case 3:
                        $this->assertEquals('actor-multiple-sources', $changeAction->actorId);
                        $this->assertEquals(['source-def', 'source-ghi', 'source-jkl'], $changeAction->sourceIds);
                        break;
                }

                return new ChangeActorStatusOutputDto(actor: $mockActor, sources: []);
            });

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
        $this->assertEquals('Batch operation completed successfully: All 3 actor(s) processed.', $result['message']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['results']);
        $this->assertCount(0, $result['errors']);
    }

    public function testInvokeWithSourceIdsCausingErrors(): void
    {
        $actors = [
            new BatchChangeActorDataDto(
                id: 'actor-valid-sources',
                sourceIds: ['valid-source-1', 'valid-source-2'],
                watchFileId: 'watch-file-1',
            ),
            new BatchChangeActorDataDto(
                id: 'actor-invalid-sources',
                sourceIds: ['invalid-source-1'],
                watchFileId: 'watch-file-1',
            ),
            new BatchChangeActorDataDto(
                id: 'actor-no-sources-but-fails',
                sourceIds: [],
                watchFileId: 'watch-file-1',
            ),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->exactly(3))
            ->method('__invoke')
            ->willReturnCallback(function (ChangeActorStatusAction $changeAction) {
                $mockActor = $this->createStub(Actor::class);

                if ('actor-invalid-sources' === $changeAction->actorId) {
                    throw new \Exception('Invalid source ID provided: invalid-source-1');
                }

                if ('actor-no-sources-but-fails' === $changeAction->actorId) {
                    throw new \Exception('Actor processing failed for unknown reason');
                }

                return new ChangeActorStatusOutputDto(actor: $mockActor, sources: []);
            });

        $result = $this->handler->__invoke($action);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('partial success', $result['message']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(1, $result['processed']);
        $this->assertEquals(2, $result['failed']);
        $this->assertCount(1, $result['results']);
        $this->assertCount(2, $result['errors']);

        $this->assertEquals('actor-valid-sources', $result['results'][0]['id']);
        $this->assertTrue($result['results'][0]['success']);

        $errorMessages = array_column($result['errors'], 'error');
        $this->assertIsString($errorMessages[0]);
        $this->assertIsString($errorMessages[1]);
        $this->assertStringContainsString('Invalid source ID provided', $errorMessages[0]);
        $this->assertStringContainsString('Actor processing failed', $errorMessages[1]);

        $errorActorIds = array_column($result['errors'], 'id');
        $this->assertContains('actor-invalid-sources', $errorActorIds);
        $this->assertContains('actor-no-sources-but-fails', $errorActorIds);
    }

    public function testInvokePreservesSourceIdsOrderAndContent(): void
    {
        $expectedSourceIds = ['source-z', 'source-a', 'source-m', 'source-b'];
        $actors = [
            new BatchChangeActorDataDto(
                id: 'actor-ordered-sources',
                sourceIds: $expectedSourceIds,
                watchFileId: 'watch-file-1',
            ),
        ];

        $action = new BatchChangeActorStatusAction($actors);

        $this->changeActorStatusHandler
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->callback(function (ChangeActorStatusAction $changeAction) use ($expectedSourceIds) {
                return $changeAction->sourceIds === $expectedSourceIds;
            }))
            ->willReturn(new ChangeActorStatusOutputDto(actor: $this->createStub(Actor::class), sources: []));

        $result = $this->handler->__invoke($action);

        $this->assertTrue($result['success']);
    }
}
