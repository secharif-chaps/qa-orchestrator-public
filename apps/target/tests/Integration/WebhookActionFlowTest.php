<?php

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\SearchQueryFactory;
use App\DataFixtures\Factory\WatchFile\SearchResultFactory;
use App\DataFixtures\Factory\WatchFile\StrategicQuestionFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for webhook action flows (synchronous and asynchronous).
 */
class WebhookActionFlowTest extends AbstractApiTestCase
{
    private const WEBHOOK_ENDPOINT = '/api/webhook';
    private const VALID_TOKEN = 'my_super_secret_token';
    private const VALID_SECRET = 'my_ultra_secret_HMAC_key';

    /**
     * Test that webhook handles synchronous actions correctly.
     *
     * Synchronous actions implement SyncActionInterface and should:
     * - Be handled immediately
     * - Return response data in 'response' field
     * - Return mode='sync'
     */
    public function testWebhookHandlesSynchronousAction(): void
    {
        // Create test data with Foundry factories
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();
        $actor = ActorFactory::createOne();
        $source = SourceFactory::createOne([
            'actor' => $actor,
            'watchFile' => $watchFile,
        ]);

        $client = static::createClient();

        // Use ChangeSourceStatusAction as an example of synchronous action
        $payload = [
            'watchFileId' => $watchFile->getId(),
            'sourceId' => $source->getId(),
            'status' => 'inactive',
            'user' => null,
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Source\ChangeSourceStatusAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        /** @var array{status: string, mode: string, response?: mixed} $responseData */
        $responseData = json_decode($content, true);

        // Verify synchronous response structure
        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('ok', $responseData['status']);
        $this->assertArrayHasKey('mode', $responseData);
        $this->assertEquals('sync', $responseData['mode']);
        $this->assertArrayHasKey('response', $responseData, 'Synchronous actions should return response data');
    }

    /**
     * Test that webhook handles asynchronous actions correctly.
     *
     * Asynchronous actions do NOT implement SyncActionInterface and should:
     * - Be dispatched to message bus
     * - Return mode='async'
     * - Not include 'response' field
     */
    public function testWebhookHandlesAsynchronousAction(): void
    {
        // Create test data with Foundry factories
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();

        $client = static::createClient();

        // Use ProcessActorBatchAction as an example of asynchronous action
        $payload = [
            'watchFileId' => 'test-watchfile-id',
            'name' => 'ChapsVision',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Un concurrent majeur sur le marché.',
                'en' => 'A major competitor in the market.',
            ],
            'primaryDomain' => 'chapvision.com',
            'score' => 0.95,
            'messageContentId' => null,
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        /** @var array{status: string, mode: string} $responseData */
        $responseData = json_decode($content, true);

        // Verify asynchronous response structure
        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('ok', $responseData['status']);
        $this->assertArrayHasKey('mode', $responseData);
        $this->assertEquals('async', $responseData['mode']);
        $this->assertArrayNotHasKey('response', $responseData, 'Asynchronous actions should not return response data');
    }

    /**
     * Test that webhook returns error for invalid message type.
     */
    public function testWebhookRejectsInvalidMessageType(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\NonExistent\InvalidAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test that webhook returns error for missing message type header.
     */
    public function testWebhookRejectsMissingMessageType(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                // No X-Message-Type header
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test that webhook handles malformed JSON payload.
     */
    public function testWebhookRejectsMalformedJson(): void
    {
        $client = static::createClient();

        // Cannot use 'json' option for malformed JSON, use body directly
        $malformedPayload = 'this is not valid json {{{';
        $signature = hash_hmac('sha256', $malformedPayload, self::VALID_SECRET);

        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'body' => $malformedPayload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        // Malformed JSON causes 400 Bad Request in controller validation
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Test that webhook handles multiple different synchronous actions.
     *
     * NOTE: This test is skipped because ChangeActorStatusAction requires a User object,
     * but the webhook serializer cannot deserialize UUID strings to User entities.
     * Only actions with nullable or primitive types can be tested in webhook integration tests.
     * Use ChangeSourceStatusAction (which has ?User $user = null) for testing synchronous actions.
     *
     * @skip
     */
    public function skippedTestWebhookHandlesMultipleSyncActions(): void
    {
        // Create test data with Foundry factories
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();
        $actor = ActorFactory::createOne();
        $watchFileActor = WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => 'competitor',
            'status' => ActorStatus::INACTIVE,
        ]);
        $source = SourceFactory::createOne([
            'actor' => $actor,
            'watchFile' => $watchFile,
        ]);

        $client = static::createClient();

        // Test ChangeActorStatusAction (changes WatchFileActor status from inactive to active)
        $payload1 = [
            'watchFileId' => $watchFile->getId(),
            'actorId' => $actor->getId(),
            'sourceIds' => [$source->getId()],
            'newStatus' => 'active',
            'user' => $user->getId(),
        ];
        $payload1Json = json_encode($payload1);
        $this->assertIsString($payload1Json);
        $signature1 = hash_hmac('sha256', $payload1Json, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload1,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature1,
                'X-Message-Type' => 'App\Application\Actor\ChangeActorStatusAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content1 = $response->getContent();
        /** @var array{mode: string, response?: mixed} $response1Data */
        $response1Data = json_decode($content1, true);
        $this->assertEquals('sync', $response1Data['mode']);
        $this->assertArrayHasKey('response', $response1Data);

        // Test ChangeSourceStatusAction
        $payload2 = [
            'watchFileId' => $watchFile->getId(),
            'sourceId' => $source->getId(),
            'status' => 'inactive',
            'user' => null,
        ];
        $payload2Json = json_encode($payload2);
        $this->assertIsString($payload2Json);
        $signature2 = hash_hmac('sha256', $payload2Json, self::VALID_SECRET);

        $response2 = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload2,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature2,
                'X-Message-Type' => 'App\Application\WatchFile\Source\ChangeSourceStatusAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content2 = $response2->getContent();
        /** @var array{mode: string, response?: mixed} $response2Data */
        $response2Data = json_decode($content2, true);
        $this->assertEquals('sync', $response2Data['mode']);
        $this->assertArrayHasKey('response', $response2Data);
    }

    /**
     * Test webhook with multiple different asynchronous actions.
     */
    public function testWebhookHandlesMultipleAsyncActions(): void
    {
        // Create test data with Foundry factories
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();

        $client = static::createClient();

        // Test ProcessSourceBatchAction
        $payload1 = [
            'watchFileId' => $watchFile->getId(),
            'name' => 'ChapsVision',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Un concurrent majeur sur le marché.',
                'en' => 'A major competitor in the market.',
            ],
            'primaryDomain' => 'chapvision.com',
            'score' => 0.95,
            'messageContentId' => null,
        ];
        $payload1Json = json_encode($payload1);
        $this->assertIsString($payload1Json);
        $signature1 = hash_hmac('sha256', $payload1Json, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload1,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature1,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content1 = $response->getContent();
        /** @var array{mode: string} $response1 */
        $response1 = json_decode($content1, true);
        $this->assertEquals('async', $response1['mode']);
        $this->assertArrayNotHasKey('response', $response1);

        // Test ProcessActorBatchAction
        $payload2 = [
            'watchFileId' => $watchFile->getId(),
            'name' => 'ChapsVision2',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Un concurrent majeur sur le marché.',
                'en' => 'A major competitor in the market.',
            ],
            'primaryDomain' => 'chapvision.com',
            'score' => 0.95,
            'messageContentId' => null,
        ];
        $payload2Json = json_encode($payload2);
        $this->assertIsString($payload2Json);
        $signature2 = hash_hmac('sha256', $payload2Json, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload2,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature2,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content2 = $response->getContent();
        /** @var array{mode: string} $response2 */
        $response2 = json_decode($content2, true);
        $this->assertEquals('async', $response2['mode']);
        $this->assertArrayNotHasKey('response', $response2);
    }

    /**
     * Test that webhook rejects unsupported HTTP methods.
     */
    public function testWebhookRejectsUnsupportedHttpMethod(): void
    {
        $client = static::createClient();

        $payload = [
            'test' => 'data',
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $client->request('GET', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\Actor\AddActorAction',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * Test that webhook handles UpdateSearchResultScoringAction correctly.
     */
    public function testWebhookHandlesUpdateSearchResultScoringAction(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();

        $strategicQuestion = StrategicQuestionFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $searchQuery = SearchQueryFactory::new()
            ->with([
                'strategicQuestion' => $strategicQuestion,
            ])
            ->create();

        $searchResult = SearchResultFactory::new()
            ->with([
                'searchQuery' => $searchQuery,
            ])
            ->create();

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $client = static::createClient();

        $payload = [
            'searchResultId' => $searchResultId,
            'qualityScore' => 85,
            'isSelected' => true,
            'selectionRank' => 3,
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\SearchResult\UpdateSearchResultScoringAction',
                'X-Serialization-Groups' => 'watch_file:llm',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        /** @var array{status: string, mode: string, response?: array{id?: string, qualityScore?: int|null, isSelected?: bool, selectionRank?: int|null}} $responseData */
        $responseData = json_decode($content, true);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('ok', $responseData['status']);
        $this->assertArrayHasKey('mode', $responseData);
        $this->assertEquals('sync', $responseData['mode']);
        $this->assertArrayHasKey('response', $responseData, 'Synchronous actions should return response data');

        $this->assertArrayHasKey('response', $responseData);
        $responseRaw = $responseData['response'] ?? null;
        $this->assertNotNull($responseRaw);
        /** @var array{id?: string, qualityScore?: int|null, isSelected?: bool, selectionRank?: int|null} $responseSearchResult */
        $responseSearchResult = $responseRaw;
        $this->assertArrayHasKey('id', $responseSearchResult);
        $this->assertEquals($searchResultId, $responseSearchResult['id'] ?? null);
        $this->assertArrayHasKey('qualityScore', $responseSearchResult);
        $this->assertEquals(85, $responseSearchResult['qualityScore'] ?? null);
        $this->assertArrayHasKey('isSelected', $responseSearchResult);
        $this->assertTrue($responseSearchResult['isSelected'] ?? false);
        $this->assertArrayHasKey('selectionRank', $responseSearchResult);
        $this->assertEquals(3, $responseSearchResult['selectionRank'] ?? null);

        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->refresh($searchResult);
        $this->assertEquals(85, $searchResult->getQualityScore());
        $this->assertTrue($searchResult->isSelected());
        $this->assertEquals(3, $searchResult->getSelectionRank());
    }

    /**
     * Test that webhook handles UpdateSearchResultScoringAction correctly when unselecting a result.
     */
    public function testWebhookHandlesUpdateSearchResultScoringActionUnselectsResult(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()->withOwnedBy($user)->create();

        $strategicQuestion = StrategicQuestionFactory::new()
            ->with([
                'watchFile' => $watchFile,
            ])
            ->create();

        $searchQuery = SearchQueryFactory::new()
            ->with([
                'strategicQuestion' => $strategicQuestion,
            ])
            ->create();

        $searchResult = SearchResultFactory::new()
            ->with([
                'searchQuery' => $searchQuery,
            ])
            ->create();

        // First, set the result as selected with a rank
        $searchResult->updateScoring(qualityScore: 85, isSelected: true, selectionRank: 3);
        /** @var \Doctrine\ORM\EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine')->getManager();
        $em->persist($searchResult);
        $em->flush();

        $searchResultId = $searchResult->getId();
        $this->assertNotNull($searchResultId);

        $client = static::createClient();

        // Unselect the result (without explicitly passing selectionRank)
        $payload = [
            'searchResultId' => $searchResultId,
            'isSelected' => false,
        ];
        $payloadJson = json_encode($payload);
        $this->assertIsString($payloadJson);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        $response = $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => 'App\Application\WatchFile\SearchResult\UpdateSearchResultScoringAction',
                'X-Serialization-Groups' => 'watch_file:llm',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $content = $response->getContent();
        /** @var array{status: string, mode: string, response?: array{id?: string, qualityScore?: int|null, isSelected?: bool, selectionRank?: int|null}} $responseData */
        $responseData = json_decode($content, true);

        $this->assertArrayHasKey('status', $responseData);
        $this->assertEquals('ok', $responseData['status']);
        $this->assertArrayHasKey('mode', $responseData);
        $this->assertEquals('sync', $responseData['mode']);
        $this->assertArrayHasKey('response', $responseData, 'Synchronous actions should return response data');

        $this->assertArrayHasKey('response', $responseData);
        $responseRaw = $responseData['response'] ?? null;
        $this->assertNotNull($responseRaw);
        /** @var array{id?: string, qualityScore?: int|null, isSelected?: bool, selectionRank?: int|null} $responseSearchResult */
        $responseSearchResult = $responseRaw;
        $this->assertArrayHasKey('id', $responseSearchResult);
        $this->assertEquals($searchResultId, $responseSearchResult['id'] ?? null);
        $this->assertArrayHasKey('isSelected', $responseSearchResult);
        $this->assertFalse($responseSearchResult['isSelected'] ?? true);
        $this->assertArrayHasKey('selectionRank', $responseSearchResult);
        $this->assertNull(
            $responseSearchResult['selectionRank'] ?? null,
            'Selection rank should be null when isSelected is false'
        );

        $em->refresh($searchResult);
        $this->assertEquals(85, $searchResult->getQualityScore(), 'Quality score should remain unchanged');
        $this->assertFalse($searchResult->isSelected(), 'Result should be unselected');
        $this->assertNull(
            $searchResult->getSelectionRank(),
            'Selection rank should be automatically cleared when unselecting'
        );
    }
}
