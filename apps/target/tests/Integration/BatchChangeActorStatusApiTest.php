<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;

class BatchChangeActorStatusApiTest extends AbstractApiTestCase
{
    public function testBatchChangeActorStatusActivatesMultipleActors(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor1 = ActorFactory::createOne([
            'label' => 'Actor 1',
        ]);
        $actor2 = ActorFactory::createOne([
            'label' => 'Actor 2',
        ]);
        $actor3 = ActorFactory::createOne([
            'label' => 'Actor 3',
        ]);

        $watchFileActor1 = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor1,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $watchFileActor2 = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor2,
            'type' => ActorType::SUPPLIER,
            'status' => ActorStatus::INACTIVE,
        ]);

        $watchFileActor3 = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor3,
            'type' => ActorType::PARTNER,
            'status' => ActorStatus::INACTIVE,
        ]);

        $source1 = SourceFactory::createOne([
            'actor' => $actor1,
        ]);
        $source2 = SourceFactory::createOne([
            'actor' => $actor2,
        ]);
        $source3 = SourceFactory::createOne([
            'actor' => $actor3,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor1->getId(),
                        'sourceIds' => [],
                    ],
                    [
                        'id' => $actor2->getId(),
                        'sourceIds' => [],
                    ],
                    [
                        'id' => $actor3->getId(),
                        'sourceIds' => [],
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('processed', $data);
        $this->assertArrayHasKey('failed', $data);

        $this->assertTrue($data['success']);
        $this->assertStringContainsString('completed successfully', $data['message']);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals(3, $data['processed']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(3, $data['results']);
        $this->assertCount(0, $data['errors']);

        foreach ($data['results'] as $result) {
            $this->assertTrue($result['success']);
            $this->assertEquals('active', $result['status']);
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('watchFileId', $result);
        }
    }

    public function testBatchChangeActorStatusRequiresAuthentication(): void
    {
        $watchFile = WatchFileFactory::createOne();

        $client = self::createClient();
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [[
                    'id' => 'some-actor-id',
                    'sourceIds' => [],
                ]],
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testBatchChangeActorStatusRequiresWatchFileAccess(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [[
                    'id' => 'some-actor-id',
                    'sourceIds' => [],
                ]],
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testBatchChangeActorStatusWithSharedWatchFile(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileUserFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $sharedUser,
            'role' => WatchFileUserRole::EDITOR,
        ]);

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($sharedUser);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [[
                    'id' => $actor->getId(),
                    'sourceIds' => [],
                ]],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['processed']);
    }

    public function testBatchChangeActorStatusWithEmptyActorsArray(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422); // Validation error
    }

    public function testBatchChangeActorStatusWithInvalidActorIds(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => 'invalid-id-1',
                        'sourceIds' => [],
                    ],
                    [
                        'id' => 'invalid-id-2',
                        'sourceIds' => [],
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertFalse($data['success']);
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(0, $data['processed']);
        $this->assertEquals(2, $data['failed']);
        $this->assertCount(0, $data['results']);
        $this->assertCount(2, $data['errors']);
    }

    public function testBatchChangeActorStatusWithMixedValidAndInvalidIds(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $validActor = ActorFactory::createOne([
            'label' => 'Valid Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $validActor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $validActor->getId(),
                        'sourceIds' => [],
                    ],
                    [
                        'id' => 'invalid-id',
                        'sourceIds' => [],
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertFalse($data['success']); // Not successful because there were errors
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(1, $data['processed']);
        $this->assertEquals(1, $data['failed']);
        $this->assertCount(1, $data['results']);
        $this->assertCount(1, $data['errors']);

        $this->assertTrue($data['results'][0]['success']);
        $this->assertEquals($validActor->getId(), $data['results'][0]['id']);
    }

    public function testBatchChangeActorStatusWithMissingRequestBody(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status");

        $this->assertResponseStatusCodeSame(422);
    }

    public function testBatchChangeActorStatusWithInvalidWatchFileId(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', '/api/watch_files/invalid-id/actors/batch-change-status', [
            'json' => [
                'actors' => [[
                    'id' => 'some-actor-id',
                    'sourceIds' => [],
                ]],
            ],
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testBatchChangeActorStatusWithNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', '/api/watch_files/00000000-0000-0000-0000-000000000000/actors/batch-change-status', [
            'json' => [
                'actors' => [[
                    'id' => 'some-actor-id',
                    'sourceIds' => [],
                ]],
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testBatchChangeActorStatusWithInvalidJsonFormat(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => 'not-an-array',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422); // Validation error
    }

    public function testBatchChangeActorStatusWithNoSourceIds(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor->getId(),
                        'sourceIds' => [],
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals(1, $data['processed']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(1, $data['results']);
        $this->assertCount(0, $data['errors']);

        $this->assertEquals([], $data['results'][0]['data']->sourceIds ?? []);
    }

    public function testBatchChangeActorStatusWithSingleSourceId(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $source = SourceFactory::createOne([
            'actor' => $actor,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor->getId(),
                        'sourceIds' => [$source->getId()],
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals(1, $data['processed']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(1, $data['results']);
        $this->assertCount(0, $data['errors']);
    }

    public function testBatchChangeActorStatusWithMultipleSourceIds(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $source1 = SourceFactory::createOne([
            'actor' => $actor,
        ]);
        $source2 = SourceFactory::createOne([
            'actor' => $actor,
        ]);
        $source3 = SourceFactory::createOne([
            'actor' => $actor,
        ]);

        $sourceIds = [$source1->getId(), $source2->getId(), $source3->getId()];

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor->getId(),
                        'sourceIds' => $sourceIds,
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals(1, $data['total']);
        $this->assertEquals(1, $data['processed']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(1, $data['results']);
        $this->assertCount(0, $data['errors']);
    }

    public function testBatchChangeActorStatusWithMixedSourceIdsScenarios(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor1 = ActorFactory::createOne([
            'label' => 'Actor No Sources',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor1,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $actor2 = ActorFactory::createOne([
            'label' => 'Actor Single Source',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor2,
            'type' => ActorType::SUPPLIER,
            'status' => ActorStatus::INACTIVE,
        ]);
        $source2 = SourceFactory::createOne([
            'actor' => $actor2,
        ]);

        $actor3 = ActorFactory::createOne([
            'label' => 'Actor Multiple Sources',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor3,
            'type' => ActorType::PARTNER,
            'status' => ActorStatus::INACTIVE,
        ]);
        $source3a = SourceFactory::createOne([
            'actor' => $actor3,
        ]);
        $source3b = SourceFactory::createOne([
            'actor' => $actor3,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor1->getId(),
                        'sourceIds' => [],
                    ],
                    [
                        'id' => $actor2->getId(),
                        'sourceIds' => [$source2->getId()],
                    ],
                    [
                        'id' => $actor3->getId(),
                        'sourceIds' => [$source3a->getId(), $source3b->getId()],
                    ],
                ],
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals(3, $data['total']);
        $this->assertEquals(3, $data['processed']);
        $this->assertEquals(0, $data['failed']);
        $this->assertCount(3, $data['results']);
        $this->assertCount(0, $data['errors']);
    }

    public function testBatchChangeActorStatusWithInvalidSourceIdType(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor->getId(),
                        'sourceIds' => [123, 'valid-source'],
                    ],
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $response->toArray(false);

        $this->assertArrayHasKey('violations', $data);
        $violations = $data['violations'];

        $sourceIdViolations = array_filter($violations, function ($violation) {
            return str_contains($violation['propertyPath'] ?? '', 'sourceIds');
        });

        $this->assertNotEmpty($sourceIdViolations);

        $this->assertStringContainsString('Source ID must be a string', $data['detail'] ?? '');
    }

    public function testBatchChangeActorStatusWithNonArraySourceIds(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', "/api/watch_files/{$watchFile->getId()}/actors/batch-change-status", [
            'json' => [
                'actors' => [
                    [
                        'id' => $actor->getId(),
                        'sourceIds' => 'not-an-array',
                    ],
                ],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
        $data = $response->toArray(false);

        $this->assertArrayHasKey('violations', $data);
        $violations = $data['violations'];

        $sourceIdViolations = array_filter($violations, function ($violation) {
            return str_contains($violation['propertyPath'] ?? '', 'sourceIds')
                && str_contains($violation['message'] ?? '', 'array');
        });

        $this->assertNotEmpty($sourceIdViolations);

        $this->assertStringContainsString('Source IDs must be an array', $data['detail'] ?? '');
    }
}
