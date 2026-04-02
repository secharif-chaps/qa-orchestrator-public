<?php

declare(strict_types=1);

namespace App\Tests\Integration\Source;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Source\SourceStatus;
use App\Tests\Integration\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SourceApiTest extends AbstractApiTestCase
{
    /**
     * Given a WatchFile with 30 sources : 20 actives and 10 inactives
     * When the API GET /api/watch_files/{id}/sources?active=true
     * Then only the 20 active sources are returned with pagination.
     */
    public function testGetSourcesFilteredByActiveStatus(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        // Create 20 active sources
        for ($i = 0; $i < 20; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus(SourceStatus::ACTIVE)
                ->create();
        }

        // Create 10 inactive sources
        for ($i = 0; $i < 10; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus(SourceStatus::INACTIVE)
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources', [
            'query' => [
                'active' => 'true',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        // The response format depends on the API Platform serialization
        $member = $json['hydra:member'] ?? $json['member'] ?? $json;
        $this->assertCount(20, $member);
        $this->assertEquals(20, $json['hydra:totalItems'] ?? $json['totalItems']);

        // Verify all returned sources are active
        // Note: active is a computed property based on status
        foreach ($member as $source) {
            // Check via the status field since active might not be serialized yet
            $this->assertArrayHasKey('status', $source, 'Source should have status property');
            $this->assertEquals(
                'active',
                $source['status'],
                \sprintf('Source %s should have active status', $source['@id'] ?? $source['id'] ?? 'unknown')
            );
        }
    }

    /**
     * Scenario 2 - Get all sources without filters.
     */
    public function testGetAllSourcesWithoutFilter(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        // Create 30 sources (mixed active and inactive)
        for ($i = 0; $i < 30; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus($i < 20 ? SourceStatus::ACTIVE : SourceStatus::INACTIVE)
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources');

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        $member = $json['hydra:member'] ?? $json['member'] ?? $json;
        // API Platform default pagination might return all items if not explicitly configured
        $this->assertGreaterThanOrEqual(20, \count($member));
        $this->assertEquals(30, $json['hydra:totalItems'] ?? $json['totalItems'] ?? \count($member));
    }

    /**
     * Scenario 3 - Get inactive sources.
     */
    public function testGetInactiveSources(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        for ($i = 0; $i < 15; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus(SourceStatus::ACTIVE)
                ->create();
        }

        for ($i = 0; $i < 8; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus(SourceStatus::INACTIVE)
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources', [
            'query' => [
                'active' => 'false',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        $member = $json['hydra:member'] ?? $json['member'] ?? $json;
        $this->assertCount(8, $member);
        $this->assertEquals(8, $json['hydra:totalItems'] ?? $json['totalItems']);

        // Verify all returned sources are inactive
        foreach ($member as $source) {
            $this->assertEquals('inactive', $source['status']);
        }
    }

    /**
     * Scenario 5 - Toggle Source Activation (Activation).
     */
    public function testToggleSourceActivation(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        $source = SourceFactory::new()
            ->withWatchFile($watchFile)
            ->withActor($actor)
            ->withStatus(SourceStatus::INACTIVE)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/source/' . $source->getId() . '/change-status/',
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        $this->assertEquals('active', $json['status']);
        $this->assertArrayHasKey('updatedAt', $json);
    }

    /**
     * Scenario 6 - Toggle Source Activation (Deactivation).
     */
    public function testToggleSourceDeactivation(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        $source = SourceFactory::new()
            ->withWatchFile($watchFile)
            ->withActor($actor)
            ->withStatus(SourceStatus::ACTIVE)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/source/' . $source->getId() . '/change-status/',
            [
                'json' => [
                    'status' => 'inactive',
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        $this->assertEquals('inactive', $json['status']);
        $this->assertArrayHasKey('updatedAt', $json);
    }

    /**
     * Scenario 7 - Configurable Pagination.
     */
    public function testCustomPagination(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        // Create 50 sources
        for ($i = 0; $i < 50; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources', [
            'query' => [
                'page' => 2,
                'itemsPerPage' => 3,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        $member = $json['hydra:member'] ?? $json['member'] ?? $json;
        $this->assertCount(3, $member);
        $this->assertEquals(50, $json['hydra:totalItems'] ?? $json['totalItems']);
    }

    /**
     * Scenario 8 - Filter + Pagination Combination.
     */
    public function testFilterWithPagination(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();
        $actor = ActorFactory::new()->create();

        // Create 20 active sources
        for ($i = 0; $i < 20; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus(SourceStatus::ACTIVE)
                ->create();
        }

        // Create 10 inactive sources
        for ($i = 0; $i < 10; ++$i) {
            SourceFactory::new()
                ->withWatchFile($watchFile)
                ->withActor($actor)
                ->withStatus(SourceStatus::INACTIVE)
                ->create();
        }

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources', [
            'query' => [
                'active' => 'true',
                'page' => 2,
                'itemsPerPage' => 10,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $json = $response->toArray();

        $member = $json['hydra:member'] ?? $json['member'] ?? $json;
        $this->assertCount(10, $member);
        $this->assertEquals(20, $json['hydra:totalItems'] ?? $json['totalItems']);

        // Verify all are active
        foreach ($member as $source) {
            $this->assertEquals('active', $source['status']);
        }
    }

    /**
     * Error Case - Non-existent WatchFile.
     */
    public function testGetSourcesWithInvalidWatchFileId(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $client = $this->createAuthenticatedClient($user);

        // Use a valid UUID format but non-existent ID
        $client->request('GET', '/api/watch_files/00000000-0000-0000-0000-000000000000/sources');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * Error Case - Toggle Non-existent Source.
     */
    public function testToggleNonExistentSource(): void
    {
        $user = UserFactory::new()->defaultBasilUser()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        // Use a valid UUID format but non-existent ID
        // Security check runs before resource resolution, returning 403 instead of 404
        $client->request(
            'POST',
            '/api/watch_files/' . $watchFile->getId() . '/source/00000000-0000-0000-0000-000000000000/change-status/',
            [
                'json' => [
                    'status' => 'active',
                ],
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Error Case - Insufficient Permissions.
     */
    public function testAccessDeniedForUnauthorizedUser(): void
    {
        $owner = UserFactory::new()->create();
        $otherUser = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($otherUser);
        $client->request('GET', '/api/watch_files/' . $watchFile->getId() . '/sources');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
