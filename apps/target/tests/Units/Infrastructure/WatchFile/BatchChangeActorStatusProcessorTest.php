<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use App\Application\Actor\BatchChangeActorStatusAction;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\BatchChangeActorStatusProcessor;
use App\Tests\Utils\MockHelpersTrait;
use App\UserInterface\Dto\Actor\BatchChangeActorStatusInputDto;
use App\UserInterface\Dto\Actor\BatchChangeActorStatusOutputDto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class BatchChangeActorStatusProcessorTest extends TestCase
{
    use MockHelpersTrait;
    private MessageBusInterface&Stub $messageBus;
    private NullWatchFileGateway $watchFileGateway;
    private Security&Stub $security;
    private BatchChangeActorStatusProcessor $processor;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createStub(Security::class);
        $this->operation = $this->createStub(Operation::class);

        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $this->processor = new BatchChangeActorStatusProcessor(
            $this->messageBus,
            $this->watchFileGateway,
            $this->security
        );
    }

    /**
     * @param string[] $sourceIds
     *
     * @return array{id: string, sourceIds: list<string>}
     */
    private function createActorData(string $id, array $sourceIds = []): array
    {
        return [
            'id' => $id,
            'sourceIds' => array_values($sourceIds),
        ];
    }

    private function mockWatchFileAccess(string $watchFileId): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));

        // Use reflection to set the ID since it's normally set by the database
        $reflection = new \ReflectionClass($watchFile);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($watchFile, $watchFileId);

        // Store the watchfile in our null gateway
        $this->watchFileGateway->save($watchFile);

        /** @var Security&MockObject $security */
        $security = $this->security;
        $security->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_EDIT', $watchFile)
            ->willReturn(true);
    }

    public function testProcessWithValidData(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440010';
        $actors = [
            $this->createActorData('actor-1'),
            $this->createActorData('actor-2'),
            $this->createActorData('actor-3'),
        ];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => true,
            'message' => 'Batch operation completed successfully: All 3 actor(s) processed.',
            'results' => [
                [
                    'id' => 'actor-1',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
                [
                    'id' => 'actor-2',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
                [
                    'id' => 'actor-3',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
            ],
            'errors' => [],
            'total' => 3,
            'processed' => 3,
            'failed' => 0,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (BatchChangeActorStatusAction $action) use ($watchFileId) {
                // Check that we have 3 actors
                if (3 !== \count($action->actors)) {
                    return false;
                }

                $expectedData = [
                    [
                        'id' => 'actor-1',
                        'sourceIds' => [],
                        'watchFileId' => $watchFileId,
                    ],
                    [
                        'id' => 'actor-2',
                        'sourceIds' => [],
                        'watchFileId' => $watchFileId,
                    ],
                    [
                        'id' => 'actor-3',
                        'sourceIds' => [],
                        'watchFileId' => $watchFileId,
                    ],
                ];

                foreach ($action->actors as $index => $actor) {
                    $expected = $expectedData[$index];
                    if ($actor->id !== $expected['id']
                        || $actor->sourceIds !== $expected['sourceIds']
                        || $actor->watchFileId !== $expected['watchFileId']) {
                        return false;
                    }
                }

                return true;
            }))
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals($expectedHandlerResult['message'], $result->message);
        $this->assertEquals($expectedHandlerResult['results'], $result->results);
        $this->assertEquals($expectedHandlerResult['errors'], $result->errors);
        $this->assertEquals(3, $result->total);
        $this->assertEquals(3, $result->processed);
        $this->assertEquals(0, $result->failed);
    }

    public function testProcessWithNullData(): void
    {
        $security = $this->createStub(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Request body is required');

        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->processor->process(null, $this->operation, [
            'watchFileId' => 'test-id',
        ], []);
    }

    public function testProcessWithMissingWatchFileId(): void
    {
        $security = $this->createStub(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $inputDto = new BatchChangeActorStatusInputDto([$this->createActorData('actor-1')]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided in the URL');

        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->processor->process(
            $inputDto,
            $this->operation,
            [], // Missing watchFileId
            []
        );
    }

    public function testProcessWithNonStringWatchFileId(): void
    {
        $security = $this->createStub(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $inputDto = new BatchChangeActorStatusInputDto([$this->createActorData('actor-1')]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->processor->process(
            $inputDto,
            $this->operation,
            [
                'watchFileId' => 123,
            ], // Integer instead of string
            []
        );
    }

    public function testProcessWithEmptyActors(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440011';
        $inputDto = new BatchChangeActorStatusInputDto([]);

        $expectedHandlerResult = [
            'success' => true,
            'message' => 'Batch operation completed: No actors to process.',
            'results' => [],
            'errors' => [],
            'total' => 0,
            'processed' => 0,
            'failed' => 0,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (BatchChangeActorStatusAction $action) {
                return [] === $action->actors;
            }))
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertTrue($result->success);
        $this->assertEmpty($result->results);
        $this->assertEmpty($result->errors);
        $this->assertEquals(0, $result->total);
    }

    public function testProcessWithPartialFailures(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440012';
        $actors = [$this->createActorData('valid-actor'), $this->createActorData('invalid-actor')];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => false,
            'message' => 'Batch operation completed with partial success: 1 actor(s) processed successfully, 1 failed.',
            'results' => [
                [
                    'id' => 'valid-actor',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
            ],
            'errors' => [
                [
                    'id' => 'invalid-actor',
                    'watchFileId' => $watchFileId,
                    'error' => 'Actor not found',
                ],
            ],
            'total' => 2,
            'processed' => 1,
            'failed' => 1,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertFalse($result->success);
        $this->assertCount(1, $result->results);
        $this->assertCount(1, $result->errors);
        $this->assertEquals(2, $result->total);
        $this->assertEquals(1, $result->processed);
        $this->assertEquals(1, $result->failed);
    }

    public function testProcessWithCompleteFailures(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440016';
        $actors = [$this->createActorData('invalid-actor-1'), $this->createActorData('invalid-actor-2')];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => false,
            'message' => 'Batch operation failed: All 2 actor(s) failed to process.',
            'results' => [],
            'errors' => [
                [
                    'id' => 'invalid-actor-1',
                    'watchFileId' => $watchFileId,
                    'error' => 'Actor not found',
                ],
                [
                    'id' => 'invalid-actor-2',
                    'watchFileId' => $watchFileId,
                    'error' => 'Actor not found',
                ],
            ],
            'total' => 2,
            'processed' => 0,
            'failed' => 2,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertFalse($result->success);
        $this->assertCount(0, $result->results);
        $this->assertCount(2, $result->errors);
        $this->assertEquals(2, $result->total);
        $this->assertEquals(0, $result->processed);
        $this->assertEquals(2, $result->failed);
    }

    public function testProcessMapsActorIdsCorrectly(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440013';
        $actors = [$this->createActorData('first-actor'), $this->createActorData('second-actor')];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => true,
            'message' => 'Batch operation completed successfully: All 2 actor(s) processed.',
            'results' => [],
            'errors' => [],
            'total' => 2,
            'processed' => 2,
            'failed' => 0,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (BatchChangeActorStatusAction $action) use ($watchFileId) {
                // Check that we have 2 actors
                if (2 !== \count($action->actors)) {
                    return false;
                }

                $expectedData = [
                    [
                        'id' => 'first-actor',
                        'sourceIds' => [],
                        'watchFileId' => $watchFileId,
                    ],
                    [
                        'id' => 'second-actor',
                        'sourceIds' => [],
                        'watchFileId' => $watchFileId,
                    ],
                ];

                foreach ($action->actors as $index => $actor) {
                    $expected = $expectedData[$index];
                    if ($actor->id !== $expected['id']
                        || $actor->sourceIds !== $expected['sourceIds']
                        || $actor->watchFileId !== $expected['watchFileId']) {
                        return false;
                    }
                }

                return true;
            }))
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);
    }

    public function testProcessWithActorsHavingSources(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440020';
        $actors = [
            $this->createActorData('actor-with-one-source', ['source-1']),
            $this->createActorData('actor-with-multiple-sources', ['source-2', 'source-3', 'source-4']),
            $this->createActorData('actor-without-sources'),
        ];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => true,
            'message' => 'Batch operation completed successfully: All 3 actor(s) processed.',
            'results' => [
                [
                    'id' => 'actor-with-one-source',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
                [
                    'id' => 'actor-with-multiple-sources',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
                [
                    'id' => 'actor-without-sources',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
            ],
            'errors' => [],
            'total' => 3,
            'processed' => 3,
            'failed' => 0,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (BatchChangeActorStatusAction $action) use ($watchFileId) {
                // Check that we have 3 actors
                if (3 !== \count($action->actors)) {
                    return false;
                }

                $expectedData = [
                    [
                        'id' => 'actor-with-one-source',
                        'sourceIds' => ['source-1'],
                        'watchFileId' => $watchFileId,
                    ],
                    [
                        'id' => 'actor-with-multiple-sources',
                        'sourceIds' => ['source-2', 'source-3', 'source-4'],
                        'watchFileId' => $watchFileId,
                    ],
                    [
                        'id' => 'actor-without-sources',
                        'sourceIds' => [],
                        'watchFileId' => $watchFileId,
                    ],
                ];

                foreach ($action->actors as $index => $actor) {
                    $expected = $expectedData[$index];
                    if ($actor->id !== $expected['id']
                        || $actor->sourceIds !== $expected['sourceIds']
                        || $actor->watchFileId !== $expected['watchFileId']) {
                        return false;
                    }
                }

                return true;
            }))
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals(3, $result->total);
        $this->assertEquals(3, $result->processed);
        $this->assertEquals(0, $result->failed);
    }

    public function testProcessWithMixedSourceConfigurations(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440021';
        $actors = [
            $this->createActorData('actor-single-source', ['source-alpha']),
            $this->createActorData(
                'actor-many-sources',
                ['source-beta', 'source-gamma', 'source-delta', 'source-epsilon']
            ),
        ];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (BatchChangeActorStatusAction $action) use ($watchFileId) {
                // Verify that source IDs are correctly mapped
                $this->assertEquals('actor-single-source', $action->actors[0]->id);
                $this->assertEquals(['source-alpha'], $action->actors[0]->sourceIds);
                $this->assertEquals($watchFileId, $action->actors[0]->watchFileId);

                $this->assertEquals('actor-many-sources', $action->actors[1]->id);
                $this->assertEquals(
                    ['source-beta', 'source-gamma', 'source-delta', 'source-epsilon'],
                    $action->actors[1]->sourceIds
                );
                $this->assertEquals($watchFileId, $action->actors[1]->watchFileId);

                return true;
            }))
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp([
                    'success' => true,
                    'message' => 'Batch operation completed successfully: All 2 actor(s) processed.',
                    'results' => [],
                    'errors' => [],
                    'total' => 2,
                    'processed' => 2,
                    'failed' => 0,
                ], 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertTrue($result->success);
    }

    public function testProcessWithDuplicateSourceIds(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440022';
        $actors = [
            $this->createActorData('actor-duplicate-sources', ['source-1', 'source-2', 'source-1', 'source-3']),
        ];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (BatchChangeActorStatusAction $action) {
                $expectedSourceIds = ['source-1', 'source-2', 'source-1', 'source-3'];
                $this->assertEquals($expectedSourceIds, $action->actors[0]->sourceIds);

                return true;
            }))
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp([
                    'success' => true,
                    'message' => 'Batch operation completed successfully: All 1 actor(s) processed.',
                    'results' => [],
                    'errors' => [],
                    'total' => 1,
                    'processed' => 1,
                    'failed' => 0,
                ], 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertTrue($result->success);
    }

    public function testProcessWithInvalidSourcesFailures(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440023';
        $actors = [
            $this->createActorData('actor-with-invalid-source', ['invalid-source-1']),
            $this->createActorData('actor-with-valid-source', ['valid-source-1']),
            $this->createActorData('actor-with-mixed-sources', ['valid-source-2', 'invalid-source-2']),
        ];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => false,
            'message' => 'Batch operation completed with partial success: 1 actor(s) processed successfully, 2 failed.',
            'results' => [
                [
                    'id' => 'actor-with-valid-source',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
            ],
            'errors' => [
                [
                    'index' => 0,
                    'id' => 'actor-with-invalid-source',
                    'watchFileId' => $watchFileId,
                    'error' => 'Source invalid-source-1 not found',
                ],
                [
                    'index' => 2,
                    'id' => 'actor-with-mixed-sources',
                    'watchFileId' => $watchFileId,
                    'error' => 'Source invalid-source-2 not found',
                ],
            ],
            'total' => 3,
            'processed' => 1,
            'failed' => 2,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertFalse($result->success);
        $this->assertCount(1, $result->results);
        $this->assertCount(2, $result->errors);
        $this->assertEquals(3, $result->total);
        $this->assertEquals(1, $result->processed);
        $this->assertEquals(2, $result->failed);
    }

    public function testProcessWithSourcePermissionFailures(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440024';
        $actors = [
            $this->createActorData('actor-forbidden-source', ['forbidden-source-1']),
            $this->createActorData('actor-allowed-source', ['allowed-source-1']),
        ];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => false,
            'message' => 'Batch operation completed with partial success: 1 actor(s) processed successfully, 1 failed.',
            'results' => [
                [
                    'id' => 'actor-allowed-source',
                    'watchFileId' => $watchFileId,
                    'status' => 'active',
                    'success' => true,
                ],
            ],
            'errors' => [
                [
                    'index' => 0,
                    'id' => 'actor-forbidden-source',
                    'watchFileId' => $watchFileId,
                    'error' => 'Access denied to source forbidden-source-1',
                ],
            ],
            'total' => 2,
            'processed' => 1,
            'failed' => 1,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertFalse($result->success);
        $this->assertCount(1, $result->results);
        $this->assertCount(1, $result->errors);
        $this->assertEquals(2, $result->total);
        $this->assertEquals(1, $result->processed);
        $this->assertEquals(1, $result->failed);
    }

    public function testProcessWithEmptySourcesArrayFailure(): void
    {
        $security = $this->createMockWithExpectations(Security::class);
        $messageBus = $this->createMockWithExpectations(MessageBusInterface::class);
        $this->security = $security;
        $this->messageBus = $messageBus;
        $this->buildProcessor();

        $watchFileId = '550e8400-e29b-41d4-a716-446655440025';
        $actors = [$this->createActorData('actor-requiring-sources', [])];

        $inputDto = new BatchChangeActorStatusInputDto($actors);

        $expectedHandlerResult = [
            'success' => false,
            'message' => 'Batch operation failed: All 1 actor(s) failed to process.',
            'results' => [],
            'errors' => [
                [
                    'index' => 0,
                    'id' => 'actor-requiring-sources',
                    'watchFileId' => $watchFileId,
                    'error' => 'Actor requires at least one source to be activated',
                ],
            ],
            'total' => 1,
            'processed' => 0,
            'failed' => 1,
        ];

        $messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope($this->createStub(BatchChangeActorStatusAction::class), [
                new HandledStamp($expectedHandlerResult, 'handler'),
            ]));

        $this->mockWatchFileAccess($watchFileId);

        $result = $this->processor->process($inputDto, $this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(BatchChangeActorStatusOutputDto::class, $result);
        $this->assertFalse($result->success);
        $this->assertCount(0, $result->results);
        $this->assertCount(1, $result->errors);
        $this->assertEquals(1, $result->total);
        $this->assertEquals(0, $result->processed);
        $this->assertEquals(1, $result->failed);
    }
}
