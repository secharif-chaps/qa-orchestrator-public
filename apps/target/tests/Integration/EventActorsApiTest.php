<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileActor;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use Doctrine\ORM\EntityManagerInterface;

class EventActorsApiTest extends AbstractApiTestCase
{
    private EntityManagerInterface $entityManager;
    private WatchFileActivityGatewayInterface $activityGateway;
    private WatchFile $watchFile;
    private User $user;
    private Actor $actor;
    private WatchFileActor $watchFileActor;
    private WatchFileActivity $actorAddedEvent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->activityGateway = self::getContainer()->get(WatchFileActivityGatewayInterface::class);

        // Create test data using factories
        $this->user = UserFactory::createOne([
            'email' => 'test@example.com',
            'firstName' => 'Test',
            'lastName' => 'User',
        ]);
        $this->watchFile = WatchFileFactory::createOne([
            'createdBy' => $this->user,
        ]);

        // Add user to watchfile users so they have access
        WatchFileUserFactory::createOne([
            'user' => $this->user,
            'watchFile' => $this->watchFile,
            'role' => WatchFileUserRole::OWNER,
        ]);
        $this->actor = ActorFactory::createOne([
            'label' => 'Test Actor',
            'primaryDomain' => 'example.com',
        ]);

        // Ensure we have fresh entities from database
        $this->entityManager->flush(); // Flush factories first
        $this->entityManager->clear(); // Clear identity map

        // Reload entities from database
        $actor = $this->entityManager->find(Actor::class, $this->actor->getId());
        $watchFile = $this->entityManager->find(WatchFile::class, $this->watchFile->getId());
        $user = $this->entityManager->find(User::class, $this->user->getId());

        $this->assertNotNull($actor, 'Actor should be found in database');
        $this->assertNotNull($watchFile, 'WatchFile should be found in database');
        $this->assertNotNull($user, 'User should be found in database');

        $this->watchFileActor = new WatchFileActor($actor, $watchFile);
        $this->watchFileActor->setType(ActorType::OTHER);
        $this->watchFileActor->setScore(0.8);
        $this->watchFileActor->setExplanation(new TranslatedText('Acteur test', 'Test actor'));
        $this->entityManager->persist($this->watchFileActor);

        // Create an ACTOR_ADDED event using reloaded entities
        $this->actorAddedEvent = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::ACTOR_ADDED,
            [
                'actor_id' => $actor->getId(),
                'actor_name' => $actor->getLabel(),
                'actor_type' => ActorType::OTHER,
                'explanation' => [
                    'fr' => 'Acteur test',
                    'en' => 'Test actor',
                ],
                'score' => 0.8,
                'primary_domain' => 'example.com',
            ],
            $watchFile->getOrganisation(),
        );
        $this->activityGateway->save($this->actorAddedEvent);

        // Flush all persisted entities to database
        $this->entityManager->flush();
    }

    public function testGetEventActorsSuccess(): void
    {
        $client = $this->createAuthenticatedClient($this->user);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $this->watchFile->getId(),
            $this->actorAddedEvent->getId()
        ));

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        /** @var array{event: array{id: string, type: string, timestamp: string, user: array{id: string, name: string}, context: string}, actors: list<array{id: string, actor: array{id: string, label: string, primaryDomain: string}, type: string, explanations: array{fr: string, en: string}, status: string}>, count: int} $data */
        $data = $response->toArray();

        // Verify event data structure
        $this->assertArrayHasKey('event', $data);
        $event = $data['event'];
        $this->assertEquals($this->actorAddedEvent->getId(), $event['id']);
        $this->assertEquals('WATCHFILE_ACTOR_ADDED', $event['type']);
        $this->assertArrayHasKey('timestamp', $event);
        $this->assertArrayHasKey('user', $event);
        $this->assertEquals($this->user->getId(), $event['user']['id']);
        $this->assertEquals('Test User', $event['user']['name']);
        $this->assertArrayHasKey('context', $event);
        $this->assertStringContainsString('Test Actor', $event['context']);

        // Verify actors data structure
        $this->assertArrayHasKey('actors', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertEquals(1, $data['count']);

        $actors = $data['actors'];
        $this->assertCount(1, $actors);

        $actorData = $actors[0];

        // WatchFileActor now returns nested actor data
        $this->assertArrayHasKey('id', $actorData);
        $this->assertArrayHasKey('actor', $actorData);
        $this->assertEquals($this->actor->getId(), $actorData['actor']['id']);
        $this->assertEquals('Test Actor', $actorData['actor']['label']);
        $this->assertEquals('example.com', $actorData['actor']['primaryDomain']);

        // WatchFileActor specific fields
        $this->assertEquals(ActorType::OTHER->value, $actorData['type']);
        $this->assertEquals([
            'fr' => 'Acteur test',
            'en' => 'Test actor',
        ], $actorData['explanations']);

        // Status field (score and active/inactive are not in actor:read group)
        $this->assertEquals('active', $actorData['status']);
    }

    public function testGetEventActorsWithInactiveActor(): void
    {
        // Set actor as inactive (soft delete)
        $this->watchFileActor->setStatus(ActorStatus::INACTIVE);
        $this->entityManager->flush();

        // Clear and reload to ensure fresh entities for the query
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $this->watchFile->getId());
        $actor = $this->entityManager->find(Actor::class, $this->actor->getId());

        $this->assertNotNull($watchFile, 'WatchFile should be found in database');
        $this->assertNotNull($actor, 'Actor should be found in database');

        $client = $this->createAuthenticatedClient($this->user);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $watchFile->getId(),
            $this->actorAddedEvent->getId()
        ));

        $this->assertResponseIsSuccessful();

        /** @var array{actors: list<array{status: string}>, count: int} $data */
        $data = $response->toArray();
        $this->assertEquals(1, $data['count']);

        $actorData = $data['actors'][0];

        // Status should reflect the actual WatchFileActor status (inactive)
        // Note: active/inactive boolean fields are not in actor:read group
        $this->assertEquals('inactive', $actorData['status']);
    }

    public function testGetEventActorsWithNoActors(): void
    {
        // Reload entities from database (they were cleared in setUp)
        $watchFile = $this->entityManager->find(WatchFile::class, $this->watchFile->getId());
        $user = $this->entityManager->find(User::class, $this->user->getId());

        $this->assertNotNull($watchFile, 'WatchFile should be found in database');
        $this->assertNotNull($user, 'User should be found in database');

        // Create an event without actors
        $statusEvent = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::STATUS_CHANGED,
            [
                'old_status' => 'draft',
                'new_status' => 'active',
            ], $watchFile->getOrganisation());
        $this->activityGateway->save($statusEvent);

        $client = $this->createAuthenticatedClient($this->user);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $watchFile->getId(),
            $statusEvent->getId()
        ));

        $this->assertResponseIsSuccessful();

        $data = $response->toArray();
        $this->assertEquals(0, $data['count']);
        $this->assertEmpty($data['actors']);
    }

    public function testGetEventActorsNotFound(): void
    {
        $client = $this->createAuthenticatedClient($this->user);

        // Use a valid UUID format but non-existent
        $fakeEventUuid = '550e8400-e29b-41d4-a716-446655440001';
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $this->watchFile->getId(),
            $fakeEventUuid
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEventActorsWatchFileNotFound(): void
    {
        $client = $this->createAuthenticatedClient($this->user);

        // Use a valid UUID format but non-existent
        $fakeUuid = '550e8400-e29b-41d4-a716-446655440000';
        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $fakeUuid,
            $this->actorAddedEvent->getId()
        ));

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetEventActorsUnauthorized(): void
    {
        $otherUser = UserFactory::createOne([
            'email' => 'other@example.com',
        ]);

        $client = $this->createAuthenticatedClient($otherUser);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $this->watchFile->getId(),
            $this->actorAddedEvent->getId()
        ));

        $this->assertResponseStatusCodeSame(
            404
        ); // Should be 404 because getForUser will throw WatchFileNotFoundException
    }

    public function testGetEventActorsActorStatusChangedEvent(): void
    {
        // Reload entities from database (they were cleared in setUp)
        $watchFile = $this->entityManager->find(WatchFile::class, $this->watchFile->getId());
        $user = $this->entityManager->find(User::class, $this->user->getId());
        $actor = $this->entityManager->find(Actor::class, $this->actor->getId());

        $this->assertNotNull($watchFile, 'WatchFile should be found in database');
        $this->assertNotNull($user, 'User should be found in database');
        $this->assertNotNull($actor, 'Actor should be found in database');

        // Create an ACTOR_STATUS_CHANGED event
        $statusChangedEvent = new WatchFileActivity(
            $watchFile,
            $user,
            WatchFileActivityActionType::ACTOR_STATUS_CHANGED,
            [
                'actor_id' => $actor->getId(),
                'actor_name' => $actor->getLabel(),
                'old_status' => 'active',
                'status' => 'inactive',
            ],
            $watchFile->getOrganisation(),
        );
        $this->activityGateway->save($statusChangedEvent);

        $client = $this->createAuthenticatedClient($this->user);

        $response = $client->request('GET', \sprintf(
            '/api/watch_files/%s/timeline/%s/actors',
            $watchFile->getId(),
            $statusChangedEvent->getId()
        ));

        $this->assertResponseIsSuccessful();

        /** @var array{event: array{type: string, context: string}, count: int} $data */
        $data = $response->toArray();
        $this->assertEquals('WATCHFILE_ACTOR_STATUS_CHANGED', $data['event']['type']);
        $this->assertEquals(1, $data['count']);
        $this->assertStringContainsString('status changed', $data['event']['context']);
    }
}
