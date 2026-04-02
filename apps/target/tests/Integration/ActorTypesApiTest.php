<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\WatchFile\WatchFileUserRole;

class ActorTypesApiTest extends AbstractApiTestCase
{
    public function testGetActorTypesReturnsTypesWithCounts(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Create actors with different types
        $competitorActor1 = ActorFactory::createOne([
            'label' => 'Competitor 1',
        ]);
        $competitorActor2 = ActorFactory::createOne([
            'label' => 'Competitor 2',
        ]);
        $supplierActor = ActorFactory::createOne([
            'label' => 'Supplier 1',
        ]);

        // Associate actors with watch file with different types
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $competitorActor1,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::ACTIVE,
        ]);

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $competitorActor2,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::INACTIVE,
        ]);

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $supplierActor,
            'type' => ActorType::SUPPLIER,
            'status' => ActorStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertIsArray($data['types']);

        // Should have 2 types: competitor (2 actors) and supplier (1 actor)
        $this->assertCount(2, $data['types']);

        // Find competitor and supplier types
        $competitorType = null;
        $supplierType = null;

        foreach ($data['types'] as $type) {
            if (\is_array($type) && isset($type['type'])) {
                if ('competitor' === $type['type']) {
                    $competitorType = $type;
                } elseif ('supplier' === $type['type']) {
                    $supplierType = $type;
                }
            }
        }

        $this->assertNotNull($competitorType, 'Competitor type should be present');
        $this->assertNotNull($supplierType, 'Supplier type should be present');

        $this->assertEquals(2, $competitorType['count']);
        $this->assertEquals(1, $supplierType['count']);
    }

    public function testGetActorTypesRequiresAuthentication(): void
    {
        $watchFile = WatchFileFactory::createOne();

        $client = self::createClient();
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types");

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetActorTypesRequiresWatchFileAccess(): void
    {
        $owner = UserFactory::createOne();
        $unauthorizedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetActorTypesWithSharedWatchFile(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        // Share watch file with another user
        WatchFileUserFactory::createOne([
            'watchFile' => $watchFile,
            'user' => $sharedUser,
            'role' => WatchFileUserRole::EDITOR,
        ]);

        // Create an actor for the watch file
        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::PARTNER,
            'status' => ActorStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($sharedUser);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertCount(1, $data['types']);
        $this->assertEquals('partner', $data['types'][0]['type']);
        $this->assertEquals(1, $data['types'][0]['count']);
    }

    public function testGetActorTypesWithNonExistentWatchFile(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', '/api/watch_files/00000000-0000-0000-0000-000000000000/actor-types');

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetActorTypesWithInvalidWatchFileId(): void
    {
        $user = UserFactory::createOne();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', '/api/watch_files/invalid-id/actor-types');

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGetActorTypesReturnsEmptyWhenNoActors(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);
        $this->assertIsArray($data['types']);
        $this->assertCount(0, $data['types']);
    }

    public function testGetActorTypesWithNameFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Create actors with different names
        $acmeActor1 = ActorFactory::createOne([
            'label' => 'Acme Corporation',
        ]);
        $acmeActor2 = ActorFactory::createOne([
            'label' => 'Acme Industries',
        ]);
        $betaActor = ActorFactory::createOne([
            'label' => 'Beta Company',
        ]);

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $acmeActor1,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::ACTIVE,
        ]);

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $acmeActor2,
            'type' => ActorType::SUPPLIER,
            'status' => ActorStatus::ACTIVE,
        ]);

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $betaActor,
            'type' => ActorType::PARTNER,
            'status' => ActorStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types?name=acme");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);

        // Should only have actors matching "acme": competitor and supplier
        $this->assertCount(2, $data['types']);

        $types = array_column($data['types'], 'type');
        $this->assertContains('competitor', $types);
        $this->assertContains('supplier', $types);
        $this->assertNotContains('partner', $types);
    }

    public function testGetActorTypesWithEmptyNameFilter(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
        ]);

        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
            'status' => ActorStatus::ACTIVE,
        ]);

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFile->getId()}/actor-types?name=");

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertArrayHasKey('types', $data);

        // Empty name should return all types
        $this->assertCount(1, $data['types']);
    }
}
