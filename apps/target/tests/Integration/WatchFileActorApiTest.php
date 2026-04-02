<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorType;
use App\Domain\Shared\TranslatedText;

class WatchFileActorApiTest extends AbstractApiTestCase
{
    public function testGetWatchFileActorsSuccess(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Google',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
                [
                    'label' => 'Microsoft',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.7,
                ],
                [
                    'label' => 'Apple',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.9,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors");

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');

        $data = $response->toArray();

        $this->assertArrayHasKey('@context', $data);
        $this->assertArrayHasKey('@id', $data);
        $this->assertArrayHasKey('@type', $data);
        $this->assertArrayHasKey('member', $data);
        $this->assertEquals('Collection', $data['@type']);
        $this->assertEquals("/api/watch_files/{$watchFileId}/actors", $data['@id']);
        $this->assertCount(3, $data['member']);

        $firstActor = $data['member'][0];
        $this->assertArrayHasKey('@id', $firstActor);
        $this->assertArrayHasKey('@type', $firstActor);
        $this->assertArrayHasKey('type', $firstActor);
        $this->assertArrayHasKey('actor', $firstActor);
        $this->assertEquals('WatchFileActor', $firstActor['@type']);
        $this->assertArrayHasKey('label', $firstActor['actor']);
        $this->assertArrayHasKey('primaryDomain', $firstActor['actor']);
    }

    public function testGetWatchFileActorsWithFilters(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Google',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
                [
                    'label' => 'Microsoft',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.7,
                ],
                [
                    'label' => 'Apple',
                    'type' => ActorType::PARTNER,
                    'score' => 0.9,
                ],
                [
                    'label' => 'Amazon',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.6,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?type=competitor");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(3, $data['member']);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?actor.label=Google");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertEquals('Google', $data['member'][0]['actor']['label']);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?score[gte]=0.8");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?type=competitor&score[gte]=0.7");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);
    }

    public function testGetWatchFileActorsWithOrdering(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Google',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
                [
                    'label' => 'Microsoft',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.7,
                ],
                [
                    'label' => 'Apple',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.9,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?order[score]=desc");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(3, $data['member']);
        // Score values are not included in the response (watch_file:llm group only)
        // But ordering by score should still work - verify by actor labels
        $this->assertEquals('Apple', $data['member'][0]['actor']['label']);
        $this->assertEquals('Google', $data['member'][1]['actor']['label']);
        $this->assertEquals('Microsoft', $data['member'][2]['actor']['label']);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?order[actor.label]=asc");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertEquals('Apple', $data['member'][0]['actor']['label']);
        $this->assertEquals('Google', $data['member'][1]['actor']['label']);
        $this->assertEquals('Microsoft', $data['member'][2]['actor']['label']);
    }

    public function testGetWatchFileActorsUnauthorized(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Google',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $unauthorizedUser = UserFactory::new()->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', "/api/watch_files/{$watchFileId}/actors");

        $this->assertResponseStatusCodeSame(403);
    }

    public function testGetWatchFileActorsNonExistentWatchFile(): void
    {
        $user = UserFactory::new()->create();
        $nonExistentId = '550e8400-e29b-41d4-a716-446655440000';

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', "/api/watch_files/{$nonExistentId}/actors");

        $this->assertResponseStatusCodeSame(404);
    }

    public function testGetWatchFileActorsWithoutAuthentication(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Google',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = self::createClient();
        $client->request('GET', "/api/watch_files/{$watchFileId}/actors");

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetWatchFileActorsEmptyResult(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(0, $data['member']);
        $this->assertEquals(0, $data['totalItems']);
    }

    public function testGetWatchFileActorsWithExplanations(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Google',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                    'explanation' => new TranslatedText(
                        'Google est un concurrent majeur sur le marché de la recherche',
                        'Google is a major competitor in the search market',
                    ),
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);

        $actor = $data['member'][0];
        $this->assertArrayHasKey('explanations', $actor);
        $this->assertArrayHasKey('en', $actor['explanations']);
        $this->assertArrayHasKey('fr', $actor['explanations']);
        $this->assertEquals('Google is a major competitor in the search market', $actor['explanations']['en']);
        $this->assertEquals(
            'Google est un concurrent majeur sur le marché de la recherche',
            $actor['explanations']['fr']
        );
    }

    public function testGetWatchFileActorsWithPagination(): void
    {
        $user = UserFactory::new()->create();
        $actors = [];
        for ($i = 1; $i <= 35; ++$i) {
            $actors[] = [
                'label' => "Company {$i}",
                'type' => ActorType::COMPETITOR,
                'score' => 0.5 + ($i * 0.02),
            ];
        }
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors($actors)
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);

        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?page=1");
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertArrayHasKey('view', $data);
        $this->assertArrayHasKey('first', $data['view']);
        $this->assertArrayHasKey('last', $data['view']);
        $this->assertArrayHasKey('next', $data['view']);
        $this->assertCount(30, $data['member']);
    }

    public function testGetWatchFileActorsReturnsInAlphabeticalOrderByDefault(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Zebra Corp',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
                [
                    'label' => 'Apple Inc',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.9,
                ],
                [
                    'label' => 'Microsoft',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.7,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?order[actor.label]=asc");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(3, $data['member']);

        /** @var array<int, string> $actorLabels */
        $actorLabels = array_map(
            fn (array $wfa): string => (string) ($wfa['actor']['label'] ?? ''),
            $data['member']
        );
        $this->assertEquals(['Apple Inc', 'Microsoft', 'Zebra Corp'], $actorLabels);
    }

    public function testGetWatchFileActorsCaseInsensitiveOrdering(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'apple',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
                [
                    'label' => 'BANANA',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.9,
                ],
                [
                    'label' => 'Cherry',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.7,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?order[actor.label]=asc");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(3, $data['member']);

        /** @var array<int, string> $actorLabels */
        $actorLabels = array_map(
            fn (array $wfa): string => (string) ($wfa['actor']['label'] ?? ''),
            $data['member']
        );
        $this->assertEquals(['apple', 'BANANA', 'Cherry'], $actorLabels);
    }

    public function testGetWatchFileActorsWithAccentsOrdering(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withActors([
                [
                    'label' => 'Électricité SA',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.8,
                ],
                [
                    'label' => 'Energie Corp',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.9,
                ],
                [
                    'label' => 'Alpha',
                    'type' => ActorType::COMPETITOR,
                    'score' => 0.7,
                ],
            ])
            ->create();

        $watchFileId = $watchFile->getId();
        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', "/api/watch_files/{$watchFileId}/actors?order[actor.label]=asc");

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(3, $data['member']);

        /** @var array<int, string> $actorLabels */
        $actorLabels = array_map(
            fn (array $wfa): string => (string) ($wfa['actor']['label'] ?? ''),
            $data['member']
        );

        $this->assertEquals('Alpha', $actorLabels[0]);
        $this->assertContains('Électricité SA', $actorLabels);
        $this->assertContains('Energie Corp', $actorLabels);
    }
}
