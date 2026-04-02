<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\ExtractActorsFromDocumentEventsAction;
use App\Application\Document\ExtractActorsFromDocumentEventsHandler;
use App\Application\WatchFile\Actor\AddActorAction;
use App\Application\WatchFile\Actor\AddActorHandler;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorType;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Actor\NullActorGateway;
use App\Tests\Units\Infrastructure\User\NullUserGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ExtractActorsFromDocumentEventsHandlerTest extends TestCase
{
    private ExtractActorsFromDocumentEventsHandler $handler;
    private NullActorGateway $actorGateway;
    private NullWatchFileGateway $watchFileGateway;
    private NullUserGateway $userGateway;
    private AddActorHandler&MockObject $addActorHandler;
    private User $testUser;
    private WatchFile $testWatchFile;

    protected function setUp(): void
    {
        $this->actorGateway = new NullActorGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->userGateway = new NullUserGateway();
        $this->addActorHandler = $this->createMock(AddActorHandler::class);

        $this->testUser = new User(
            email: 'test@example.com',
            userName: 'testuser',
            firstName: 'Test',
            lastName: 'User'
        );
        $this->setEntityId($this->testUser, 'user-123');
        $this->userGateway->save($this->testUser);

        $this->testWatchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Test Objective', organisation: new Organisation('Test Org', 'test-org-id'),
            createdBy: $this->testUser
        );
        $this->setEntityId($this->testWatchFile, 'wf-123');
        $this->watchFileGateway->save($this->testWatchFile);

        $this->handler = new ExtractActorsFromDocumentEventsHandler(
            $this->actorGateway,
            $this->addActorHandler,
            new NullLogger()
        );

        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testExtractsUniqueActorsFromEvents(): void
    {
        $events = [
            [
                'actors' => [
                    [
                        'name' => 'Tesla Inc.',
                        'role' => 'competitor',
                    ],
                    [
                        'name' => 'SpaceX',
                        'role' => 'partner',
                    ],
                ],
            ],
            [
                'actors' => [
                    [
                        'name' => 'Tesla Inc.',
                        'role' => 'competitor',
                    ], // Duplicate
                    [
                        'name' => 'Neuralink',
                        'role' => 'subsidiary',
                    ],
                ],
            ],
        ];

        $action = new ExtractActorsFromDocumentEventsAction(
            documentId: 'doc-123',
            watchFileId: $this->testWatchFile->getId(),
            events: $events
        );

        $teslaActor = new Actor('Tesla Inc.', new Organisation('Test Org', 'test-org-id'));
        $this->setEntityId($teslaActor, 'actor-tesla');
        $this->actorGateway->save($teslaActor);

        $spacexActor = new Actor('SpaceX', new Organisation('Test Org', 'test-org-id'));
        $this->setEntityId($spacexActor, 'actor-spacex');
        $this->actorGateway->save($spacexActor);

        $neuralinkActor = new Actor('Neuralink', new Organisation('Test Org', 'test-org-id'));
        $this->setEntityId($neuralinkActor, 'actor-neuralink');
        $this->actorGateway->save($neuralinkActor);

        $actorMap = [
            'Tesla Inc.' => $teslaActor,
            'SpaceX' => $spacexActor,
            'Neuralink' => $neuralinkActor,
        ];

        $this->addActorHandler->expects($this->exactly(3))
            ->method('__invoke')
            ->willReturnCallback(function (AddActorAction $action) use ($actorMap) {
                $this->assertArrayHasKey($action->name, $actorMap);

                return $actorMap[$action->name];
            });

        $result = ($this->handler)($action);

        $this->assertCount(3, $result['successful']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['successCount']);
        $this->assertEquals(0, $result['failureCount']);
    }

    public function testHandlesActorNameNormalization(): void
    {
        $events = [
            [
                'actors' => [
                    [
                        'name' => 'Tesla  Inc.',
                        'role' => 'competitor',
                    ],
                    [
                        'name' => 'TESLA INC.',
                        'role' => 'competitor',
                    ],
                    [
                        'name' => 'Tëslà Inc.',
                        'role' => 'competitor',
                    ],
                ],
            ],
        ];

        $action = new ExtractActorsFromDocumentEventsAction(
            documentId: 'doc-123',
            watchFileId: $this->testWatchFile->getId(),
            events: $events
        );

        $teslaActor = new Actor('Tesla Inc.', new Organisation('Test Org', 'test-org-id'));
        $this->setEntityId($teslaActor, 'actor-tesla');
        $this->actorGateway->save($teslaActor);

        $this->addActorHandler->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (AddActorAction $action) use ($teslaActor) {
                $this->assertEquals('Tesla Inc.', $action->name);

                return $teslaActor;
            });

        $result = ($this->handler)($action);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['successCount']);
        $this->assertEquals(0, $result['failureCount']);
    }

    public function testSkipsInvalidActorData(): void
    {
        $events = [
            [
                'actors' => [
                    [
                        'name' => 'Valid Actor',
                        'role' => 'competitor',
                    ],
                    [
                        'name' => '',
                        'role' => 'partner',
                    ],
                    [
                        'name' => '   ',
                        'role' => 'supplier',
                    ],
                    'invalid-actor-data',
                    [
                        'role' => 'customer',
                    ],
                ],
            ],
        ];

        $action = new ExtractActorsFromDocumentEventsAction(
            documentId: 'doc-123',
            watchFileId: $this->testWatchFile->getId(),
            events: $events
        );

        $validActor = new Actor('Valid Actor', new Organisation('Test Org', 'test-org-id'));
        $this->setEntityId($validActor, 'actor-valid');
        $this->actorGateway->save($validActor);

        $this->addActorHandler->expects($this->once())
            ->method('__invoke')
            ->willReturnCallback(function (AddActorAction $action) use ($validActor) {
                return $validActor;
            });

        $result = ($this->handler)($action);

        $this->assertEquals(1, $result['successCount']);
        $this->assertEmpty($result['failed']);
    }

    public function testUsesActorTypeFromRoleString(): void
    {
        $events = [
            [
                'actors' => [
                    [
                        'name' => 'Actor 1',
                        'role' => 'competitors',
                    ],
                    [
                        'name' => 'Actor 2',
                        'role' => 'SUPPLIER',
                    ],
                    [
                        'name' => 'Actor 3',
                        'role' => 'partenaire',
                    ],
                    [
                        'name' => 'Actor 4',
                        'role' => 'unknown-role',
                    ],
                ],
            ],
        ];

        $action = new ExtractActorsFromDocumentEventsAction(
            documentId: 'doc-123',
            watchFileId: $this->testWatchFile->getId(),
            events: $events
        );

        $actors = [];
        for ($i = 1; $i <= 4; ++$i) {
            $actor = new Actor("Actor {$i}", new Organisation('Test Org', 'test-org-id'));
            $this->setEntityId($actor, "actor-{$i}");
            $this->actorGateway->save($actor);
            $actors["Actor {$i}"] = $actor;
        }

        $dispatchedTypes = [];

        $this->addActorHandler->expects($this->exactly(4))
            ->method('__invoke')
            ->willReturnCallback(function (AddActorAction $action) use ($actors, &$dispatchedTypes) {
                $dispatchedTypes[] = $action->type;

                return $actors[$action->name];
            });

        ($this->handler)($action);

        $this->assertContains(ActorType::COMPETITOR, $dispatchedTypes);
        $this->assertContains(ActorType::SUPPLIER, $dispatchedTypes);
        $this->assertContains(ActorType::PARTNER, $dispatchedTypes);
        $this->assertContains(ActorType::OTHER, $dispatchedTypes);
    }

    private function setEntityId(object $entity, string $id): void
    {
        $reflection = new \ReflectionClass($entity);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($entity, $id);
    }
}
