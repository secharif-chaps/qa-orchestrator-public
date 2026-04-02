<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorStatus;
use App\Domain\Source\SourceStatus;
use App\Domain\WatchFile\WatchFileStatus;

class WatchFileActorStatusApiTest extends AbstractApiTestCase
{
    public function testChangeActorStatusToInactive(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Create sources for the actor
        $source1 = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::ACTIVE,
            ])
            ->create();

        $source2 = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::ACTIVE,
            ])
            ->create();

        $source3 = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::INACTIVE,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();
        $source1Id = $source1->getId();
        $source2Id = $source2->getId();
        $source3Id = $source3->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [$source1Id, $source2Id],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertStringContainsString($actorId ?? '', $data['actor']); // actor is an IRI, not an object
        $this->assertCount(2, $data['sources']);

        // Verify that the specified sources were changed to AUTO_DISABLED
        $sourceIds = array_column($data['sources'], 'id');
        $this->assertContains($source1Id, $sourceIds);
        $this->assertContains($source2Id, $sourceIds);

        // Verify the status changes in the database
        $this->assertSourceStatus($source1Id, SourceStatus::AUTO_DISABLED);
        $this->assertSourceStatus($source2Id, SourceStatus::AUTO_DISABLED);
        $this->assertSourceStatus($source3Id, SourceStatus::INACTIVE); // Should remain unchanged
    }

    public function testChangeActorStatusToActive(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Create sources for the actor
        $source1 = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::AUTO_DISABLED,
            ])
            ->create();

        $source2 = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::AUTO_DISABLED,
            ])
            ->create();

        $source3 = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::INACTIVE,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();
        $source1Id = $source1->getId();
        $source2Id = $source2->getId();
        $source3Id = $source3->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [$source1Id, $source2Id],
                'status' => ActorStatus::ACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertStringContainsString($actorId ?? '', $data['actor']); // actor is an IRI, not an object
        $this->assertCount(2, $data['sources']);

        // Verify that the specified sources were changed to ACTIVE
        $sourceIds = array_column($data['sources'], 'id');
        $this->assertContains($source1Id, $sourceIds);
        $this->assertContains($source2Id, $sourceIds);

        // Verify the status changes in the database
        $this->assertSourceStatus($source1Id, SourceStatus::ACTIVE);
        $this->assertSourceStatus($source2Id, SourceStatus::ACTIVE);
        $this->assertSourceStatus($source3Id, SourceStatus::INACTIVE); // Should remain unchanged
    }

    public function testChangeActorStatusWithEmptySourceIds(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertStringContainsString($actorId ?? '', $data['actor']); // actor is an IRI, not an object
        $this->assertCount(0, $data['sources']); // No sources should be modified
    }

    public function testChangeActorStatusUnauthorized(): void
    {
        $owner = UserFactory::new()->create();
        $unauthorizedUser = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testChangeActorStatusWatchFileNotFound(): void
    {
        $user = UserFactory::new()->create();
        $nonExistentWatchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $actor = ActorFactory::new()->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request(
            'POST',
            \sprintf('/api/watch_files/%s/actors/%s/status', $nonExistentWatchFileId, $actor->getId()),
            [
                'json' => [
                    'sourceIds' => [],
                    'status' => ActorStatus::INACTIVE->value,
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(404);
    }

    public function testChangeActorStatusActorNotFound(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $nonExistentActorId = '550e8400-e29b-41d4-a716-446655440000';

        // Store ID before making the request
        $watchFileId = $watchFile->getId();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $nonExistentActorId), [
            'json' => [
                'sourceIds' => [],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testChangeActorStatusInvalidStatus(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [],
                'status' => 'invalid_status',
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangeActorStatusMissingStatus(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [],
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangeActorStatusMissingSourceIds(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('actor', $data);
        $this->assertArrayHasKey('sources', $data);
        $this->assertStringContainsString($actorId ?? '', $data['actor']); // actor is an IRI, not an object
        $this->assertCount(0, $data['sources']);
    }

    public function testChangeActorStatusEmptyRequestBody(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => null,
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testChangeActorStatusWithoutAuthentication(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = self::createClient();
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testChangeActorStatusWithNonExistentSourceIds(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        $nonExistentSourceId = '550e8400-e29b-41d4-a716-446655440000';

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [$nonExistentSourceId],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testChangeActorStatusWatchFileActive(): void
    {
        $user = UserFactory::new()->create();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        $actor = ActorFactory::new()->create();

        // Create WatchFileActor relationship
        WatchFileActorFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
            ])
            ->create();

        // Create a source for the actor
        $source = SourceFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'actor' => $actor,
                'status' => SourceStatus::ACTIVE,
            ])
            ->create();

        // Store IDs before making the request
        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();
        $sourceId = $source->getId();

        $client = $this->createAuthenticatedClient($user);
        $client->request('POST', \sprintf('/api/watch_files/%s/actors/%s/status', $watchFileId, $actorId), [
            'json' => [
                'sourceIds' => [$sourceId],
                'status' => ActorStatus::INACTIVE->value,
            ],
        ]);

        $this->assertResponseStatusCodeSame(403);
    }

    /**
     * Helper method to assert the status of a source in the database.
     */
    private function assertSourceStatus(string $sourceId, SourceStatus $expectedStatus): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $source = $entityManager->getRepository(\App\Domain\Source\Source::class)->find($sourceId);

        $this->assertNotNull($source, 'Source should exist in database');
        $this->assertEquals($expectedStatus, $source->getStatus(), 'Source status should match expected value');
    }
}
