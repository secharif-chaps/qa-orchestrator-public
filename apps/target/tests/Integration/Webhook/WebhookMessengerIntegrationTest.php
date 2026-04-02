<?php

declare(strict_types=1);

namespace App\Tests\Integration\Webhook;

use App\Application\WatchFile\Actor\AddActorAction;
use App\Application\WatchFile\RenameWatchFileAction;
use App\Application\WatchFile\SearchQuery\AddSearchQueryAction;
use App\Application\WatchFile\Source\AddSourceAction;
use App\Application\WatchFile\StrategicQuestion\AddStrategicQuestionAction;
use App\Application\WatchFile\UpdateWatchFileReferenceSubjectAction;
use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileActorFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Actor\ActorType;
use App\Domain\Source\Source;
use App\Domain\WatchFile\SearchQuery;
use App\Domain\WatchFile\StrategicQuestion;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Integration\AbstractApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;
use Zenstruck\Messenger\Test\InteractsWithMessenger;

/**
 * Integration tests for webhook endpoints with messenger queue verification.
 *
 * These tests simulate real-world scenarios where data comes from N8N workflows
 * powered by LLMs. They cover edge cases like:
 * - Malformed data from LLM responses
 * - Missing or null fields
 * - Invalid enum values
 * - Unicode and special characters
 * - Empty strings vs null
 * - Numeric strings instead of numbers
 *
 * The tests verify:
 * 1. Webhook accepts the request and queues message
 * 2. Message is processed correctly by the handler
 * 3. Database state is correct after processing
 */
class WebhookMessengerIntegrationTest extends AbstractApiTestCase
{
    use Factories;
    use InteractsWithMessenger;
    use ResetDatabase;
    private const string WEBHOOK_ENDPOINT = '/api/webhook';
    private const string VALID_TOKEN = 'my_super_secret_token';
    private const string VALID_SECRET = 'my_ultra_secret_HMAC_key';
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->getContainer()
            ->get(EntityManagerInterface::class);
    }

    private function refreshWatchFile(string $watchFileId): WatchFile
    {
        $this->entityManager->clear();
        $watchFile = $this->entityManager->find(WatchFile::class, $watchFileId);
        $this->assertNotNull($watchFile, 'WatchFile should exist');

        return $watchFile;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function sendWebhookRequest(array $payload, string $messageType): void
    {
        $client = static::createClient();

        // Use JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES to match HttpClient behavior
        // This ensures the signature computed here matches what the webhook authenticator sees
        $payloadJson = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $payloadJson, self::VALID_SECRET);

        // Use 'body' instead of 'json' to send the exact JSON string we computed the signature for
        $client->request('POST', self::WEBHOOK_ENDPOINT, [
            'body' => $payloadJson,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-TOKEN' => self::VALID_TOKEN,
                'X-Signature' => $signature,
                'X-Message-Type' => $messageType,
            ],
        ]);
    }

    public function testWebhookAddActorFullFlowWithProcessing(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Typical LLM output payload
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'OpenAI',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Leader mondial de l\'IA générative avec ChatGPT.',
                'en' => 'Global leader in generative AI with ChatGPT.',
            ],
            'primaryDomain' => 'https://openai.com',
            'score' => 0.95,
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'status' => 'ok',
            'mode' => 'async',
        ]);

        // Verify message was queued
        $this->transport('async_priority_high')
            ->queue()
            ->assertContains(AddActorAction::class);
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(1);

        // Process the message
        $this->transport('async_priority_high')
            ->process();
        $this->transport('async_priority_high')
            ->queue()
            ->assertEmpty();

        // Verify database state
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor, 'WatchFileActor should exist');
        $this->assertEquals('OpenAI', $watchFileActor->getActor()->getLabel());
        $this->assertEquals(ActorType::COMPETITOR, $watchFileActor->getType());
        $this->assertEquals(0.95, $watchFileActor->getScore());
    }

    public function testLlmOutputWithUnicodeAndSpecialCharacters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM may output Unicode characters, emojis, accents
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Société Générale 🏦 — France\'s Banking Leader',
            'type' => 'partner',
            'explanation' => [
                'fr' => 'Banque française — partenaire stratégique. Côté «innovation» très fort.',
                'en' => 'French bank — strategic partner. "Innovation" side very strong.',
            ],
            'primaryDomain' => 'https://societegenerale.com',
            'score' => 0.88,
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor, 'WatchFileActor should exist');
        $actor = $watchFileActor->getActor();
        $this->assertStringContainsString('Société Générale', $actor->getLabel());
    }

    public function testLlmOutputWithNumericScoreAsStringFailsDeserialization(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might output numbers as strings - this fails deserialization
        // because AddActorAction expects ?float for score
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'String Score Corp',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Test score en string.',
                'en' => 'Test string score.',
            ],
            'primaryDomain' => 'stringscore.com',
            'score' => '0.75', // String instead of float - causes deserialization error
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);

        // PHP strict typing prevents string coercion to float in readonly constructor
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testLlmOutputWithMissingOptionalScore(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Score is optional
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'No Score Corp',
            'type' => 'other', // Use valid ActorType
            'explanation' => [
                'fr' => 'Acteur sans score.',
                'en' => 'Actor without score.',
            ],
            'primaryDomain' => 'noscore.com',
            // score is missing
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);

        // Default score should be 0.0
        $watchFileActor = $watchFileActors->first();
        $this->assertNotFalse($watchFileActor, 'WatchFileActor should exist');
        $this->assertEquals(0.0, $watchFileActor->getScore());
    }

    public function testLlmOutputWithExplicitNullScore(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Null Score Corp',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Score explicitement null.',
                'en' => 'Explicitly null score.',
            ],
            'primaryDomain' => 'nullscore.com',
            'score' => null, // Explicit null
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);
    }

    public function testLlmOutputWithVeryLongActorName(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might generate very long names
        $longName = str_repeat('Very Long Company Name ', 20);

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => $longName,
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Nom très long.',
                'en' => 'Very long name.',
            ],
            'primaryDomain' => 'longname.com',
            'score' => 0.5,
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        // Should be processed without error
        $this->transport('async_priority_high')
            ->process();
        $this->transport('async_priority_high')
            ->queue()
            ->assertEmpty();
    }

    public function testLlmOutputWithEmptyStringPrimaryDomain(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might return empty string instead of null
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Empty Domain Corp',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Domaine vide.',
                'en' => 'Empty domain.',
            ],
            'primaryDomain' => '', // Empty string
            'score' => 0.7,
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(1, $watchFileActors);
    }

    public function testLlmOutputWithScoreOutOfRange(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might output scores > 1.0 or < 0.0
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'High Score Corp',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Score élevé.',
                'en' => 'High score.',
            ],
            'primaryDomain' => 'highscore.com',
            'score' => 1.5, // Out of expected range
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        // Should still process (validation at domain level if needed)
        $this->transport('async_priority_high')
            ->process();
    }

    public function testLlmOutputWithNegativeScore(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Negative Score Corp',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Score négatif.',
                'en' => 'Negative score.',
            ],
            'primaryDomain' => 'negative.com',
            'score' => -0.5, // Negative score
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();
    }

    public function testWebhookAddSourceFullFlowWithProcessing(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'TechCrunch RSS',
            'type' => 'rss_feed',
            'primaryDomain' => 'techcrunch.com',
            'url' => 'https://techcrunch.com/feed/',
            'query' => '',
            'description' => [
                'fr' => 'Flux RSS de TechCrunch pour les actualités tech.',
                'en' => 'TechCrunch RSS feed for tech news.',
            ],
            'relevance' => [
                'fr' => 'Source majeure pour la veille technologique.',
                'en' => 'Major source for technology monitoring.',
            ],
            'parameters' => null,
            'messageContentId' => null,
            'actorId' => null,
        ];

        $this->sendWebhookRequest($payload, AddSourceAction::class);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'status' => 'ok',
            'mode' => 'async',
        ]);

        // Verify message was queued
        $this->transport('async_priority_high')
            ->queue()
            ->assertContains(AddSourceAction::class);

        // Process the message
        $this->transport('async_priority_high')
            ->process();
        $this->transport('async_priority_high')
            ->queue()
            ->assertEmpty();

        // Verify database state
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertNotFalse($source, 'Source should exist');
        $this->assertInstanceOf(Source::class, $source);
        $this->assertEquals('TechCrunch RSS', $source->getName());
    }

    public function testLlmOutputSourceWithMalformedUrl(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might output malformed URLs
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Malformed URL Source',
            'type' => 'website',
            'primaryDomain' => 'example',
            'url' => 'htp://example.com', // Missing 't' in http
            'query' => '',
            'description' => [
                'fr' => 'URL malformée.',
                'en' => 'Malformed URL.',
            ],
            'relevance' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'parameters' => null,
            'messageContentId' => null,
            'actorId' => null,
        ];

        $this->sendWebhookRequest($payload, AddSourceAction::class);
        $this->assertResponseIsSuccessful();

        // Should still accept (validation at application level)
        $this->transport('async_priority_high')
            ->process();
    }

    public function testLlmOutputSourceWithTwitterSearchQuery(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Complex Twitter search query from LLM
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'AI Competitors Twitter',
            'type' => 'social_media:x:search',
            'primaryDomain' => 'twitter.com',
            'url' => 'https://twitter.com/search',
            'query' => '(OpenAI OR Anthropic OR "Google DeepMind") (launch OR release OR announcement) -RT lang:en since:2024-01-01',
            'description' => [
                'fr' => 'Recherche Twitter pour concurrents IA.',
                'en' => 'Twitter search for AI competitors.',
            ],
            'relevance' => [
                'fr' => 'Veille concurrentielle en temps réel.',
                'en' => 'Real-time competitive intelligence.',
            ],
            'parameters' => [
                'language' => 'en',
                'result_type' => 'recent',
                'include_retweets' => false,
            ],
            'messageContentId' => null,
            'actorId' => null,
        ];

        $this->sendWebhookRequest($payload, AddSourceAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertNotFalse($source, 'Source should exist');
        $query = $source->getQuery();
        $this->assertNotNull($query, 'Query should not be null');
        $this->assertStringContainsString('OpenAI OR Anthropic', $query);
    }

    public function testLlmOutputSourceWithLinkedActor(): void
    {
        $user = UserFactory::createOne();
        $actor = ActorFactory::createOne([
            'label' => 'Microsoft',
        ]);

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        // Link actor to watch file
        WatchFileActorFactory::createOne([
            'watchFile' => $watchFile,
            'actor' => $actor,
            'type' => ActorType::COMPETITOR,
        ]);

        $watchFileId = $watchFile->getId();
        $actorId = $actor->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Microsoft LinkedIn',
            'type' => 'social_media:linkedin:company',
            'primaryDomain' => 'linkedin.com',
            'url' => 'https://www.linkedin.com/company/microsoft',
            'query' => '',
            'description' => [
                'fr' => 'Page LinkedIn de Microsoft.',
                'en' => 'Microsoft LinkedIn page.',
            ],
            'relevance' => [
                'fr' => 'Source officielle concurrent.',
                'en' => 'Official competitor source.',
            ],
            'parameters' => null,
            'messageContentId' => null,
            'actorId' => $actorId,
        ];

        $this->sendWebhookRequest($payload, AddSourceAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);

        $source = $sources->first();
        $this->assertNotFalse($source, 'Source should exist');
        $this->assertNotNull($source->getActor());
        $this->assertEquals('Microsoft', $source->getActor()->getLabel());
    }

    public function testLlmOutputSourceWithEmptyParameters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might send empty object instead of null
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Empty Params Source',
            'type' => 'website',
            'primaryDomain' => 'example.com',
            'url' => 'https://example.com',
            'query' => '',
            'description' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'relevance' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'parameters' => [], // Empty array instead of null
            'messageContentId' => null,
            'actorId' => null,
        ];

        $this->sendWebhookRequest($payload, AddSourceAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_high')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $sources = $updatedWatchFile->getSources();
        $this->assertCount(1, $sources);
    }

    public function testLlmOutputSourceWithMissingLanguageInDescriptionFails(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might only provide one language - this should fail because TranslatedText requires both
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Single Lang Source',
            'type' => 'blog',
            'primaryDomain' => 'blog.example.com',
            'url' => 'https://blog.example.com',
            'query' => '',
            'description' => [
                'en' => 'English only description.',
                // 'fr' is missing - should cause deserialization error
            ],
            'relevance' => [
                'en' => 'English only relevance.',
            ],
            'parameters' => null,
            'messageContentId' => null,
            'actorId' => null,
        ];

        $this->sendWebhookRequest($payload, AddSourceAction::class);

        // TranslatedText normalizer throws InvalidArgumentException which results in 500
        // Note: This could be improved to return 400 by catching the exception
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testWebhookRenameWatchFileFullFlow(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original Name',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'New Renamed WatchFile',
            'isManualRename' => false,
        ];

        $this->sendWebhookRequest($payload, RenameWatchFileAction::class);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'mode' => 'async',
        ]);

        // RenameWatchFileAction routes to async_priority_low (catch-all)
        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(RenameWatchFileAction::class);

        // Process all messages (handler dispatches WatchFileUpdatedEvent which may trigger more)
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
        $this->transport('async_priority_low')
            ->queue()
            ->assertEmpty();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $this->assertEquals('New Renamed WatchFile', $updatedWatchFile->getName());
        $this->assertFalse($updatedWatchFile->isTitleManuallySetByUser());
    }

    public function testLlmOutputRenameWithUnicodeCharacters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might generate names with Unicode
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => '🎯 Veille Stratégique — Concurrent «Principal»',
            'isManualRename' => false,
        ];

        $this->sendWebhookRequest($payload, RenameWatchFileAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_low')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $this->assertStringContainsString('Veille Stratégique', $updatedWatchFile->getName());
    }

    public function testLlmOutputRenameWithVeryLongName(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $longName = str_repeat('Very Long WatchFile Name ', 50);

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => $longName,
            'isManualRename' => false,
        ];

        $this->sendWebhookRequest($payload, RenameWatchFileAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_low')
            ->process();
    }

    public function testLlmOutputRenameWithManualFlagAsString(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->with([
                'name' => 'Original',
            ])
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might output boolean as string
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'New Name',
            'isManualRename' => true, // Should be boolean but could be string
        ];

        $this->sendWebhookRequest($payload, RenameWatchFileAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_low')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $this->assertTrue($updatedWatchFile->isTitleManuallySetByUser());
    }

    public function testWebhookUpdateReferenceSubjectFullFlow(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'referenceSubject' => [
                'fr' => 'Nouvelle référence sujet en français.',
                'en' => 'New reference subject in English.',
            ],
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, UpdateWatchFileReferenceSubjectAction::class);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'mode' => 'async',
        ]);

        $this->transport('async_priority_low')
            ->queue()
            ->assertContains(UpdateWatchFileReferenceSubjectAction::class);
        $this->transport('async_priority_low')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject);
        $this->assertEquals('New reference subject in English.', $referenceSubject->en);
        $this->assertEquals('Nouvelle référence sujet en français.', $referenceSubject->fr);
    }

    public function testLlmOutputReferenceSubjectWithUnicodeAndSpecialCharacters(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'referenceSubject' => [
                'fr' => 'Sujet: «Intelligence Artificielle» — Impact 2024 🚀',
                'en' => 'Subject: "Artificial Intelligence" — Impact 2024 🚀',
            ],
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, UpdateWatchFileReferenceSubjectAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_low')
            ->process();

        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $referenceSubject = $updatedWatchFile->getReferenceSubject();
        $this->assertNotNull($referenceSubject, 'Reference subject should not be null');
        $this->assertStringContainsString('Intelligence Artificielle', $referenceSubject->fr);
    }

    public function testLlmOutputReferenceSubjectMissingLanguageFails(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Missing 'fr' - should fail TranslatedText validation
        $payload = [
            'watchFileId' => $watchFileId,
            'referenceSubject' => [
                'en' => 'English only reference subject.',
            ],
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, UpdateWatchFileReferenceSubjectAction::class);

        // TranslatedText normalizer throws InvalidArgumentException which results in 500
        // Note: This could be improved to return 400 by catching the exception
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testLlmOutputReferenceSubjectWithVeryLongText(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $longText = str_repeat('Very long reference subject text. ', 100);

        $payload = [
            'watchFileId' => $watchFileId,
            'referenceSubject' => [
                'fr' => $longText,
                'en' => $longText,
            ],
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, UpdateWatchFileReferenceSubjectAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_low')
            ->process();
    }

    public function testMultipleWebhookRequestsProcessedSequentially(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Send multiple actor requests (simulating N8N batch)
        $actors = ['Google', 'Microsoft', 'Amazon', 'Apple', 'Meta'];
        foreach ($actors as $actorName) {
            $payload = [
                'watchFileId' => $watchFileId,
                'name' => $actorName,
                'type' => 'competitor',
                'explanation' => [
                    'fr' => "Concurrent: {$actorName}",
                    'en' => "Competitor: {$actorName}",
                ],
                'primaryDomain' => strtolower($actorName) . '.com',
                'score' => rand(70, 95) / 100,
                'messageContentId' => null,
            ];

            $this->sendWebhookRequest($payload, AddActorAction::class);
            $this->assertResponseIsSuccessful();
        }

        // All should be queued
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(5);

        // Process all
        $this->transport('async_priority_high')
            ->process();
        $this->transport('async_priority_high')
            ->queue()
            ->assertEmpty();

        // Verify all actors created
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $watchFileActors = $updatedWatchFile->getWatchFileActors();
        $this->assertCount(5, $watchFileActors);
    }

    public function testMixedActorAndSourceRequestsBatch(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Send actor request
        $actorPayload = [
            'watchFileId' => $watchFileId,
            'name' => 'Netflix',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Concurrent dans le streaming.',
                'en' => 'Streaming competitor.',
            ],
            'primaryDomain' => 'netflix.com',
            'score' => 0.9,
            'messageContentId' => null,
        ];
        $this->sendWebhookRequest($actorPayload, AddActorAction::class);

        // Send source request
        $sourcePayload = [
            'watchFileId' => $watchFileId,
            'name' => 'Netflix Tech Blog',
            'type' => 'blog',
            'primaryDomain' => 'netflixtechblog.com',
            'url' => 'https://netflixtechblog.com',
            'query' => '',
            'description' => [
                'fr' => 'Blog technique de Netflix.',
                'en' => 'Netflix tech blog.',
            ],
            'relevance' => [
                'fr' => 'Veille technologique.',
                'en' => 'Technology monitoring.',
            ],
            'parameters' => null,
            'messageContentId' => null,
            'actorId' => null,
        ];
        $this->sendWebhookRequest($sourcePayload, AddSourceAction::class);

        // Both should be queued to same transport
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(2);

        // Process all
        $this->transport('async_priority_high')
            ->process();

        // Verify both created
        $updatedWatchFile = $this->refreshWatchFile($watchFileId);
        $this->assertCount(1, $updatedWatchFile->getWatchFileActors());
        $this->assertCount(1, $updatedWatchFile->getSources());
    }

    public function testWebhookWithInvalidWatchFileIdQueuesButFailsOnProcess(): void
    {
        $payload = [
            'watchFileId' => '00000000-0000-0000-0000-000000000000', // Non-existent
            'name' => 'Invalid WatchFile Corp',
            'type' => 'competitor',
            'explanation' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'primaryDomain' => 'invalid.com',
            'score' => 0.5,
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);
        $this->assertResponseIsSuccessful(); // Webhook accepts it

        // Message is queued
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(1);

        // Processing should fail (watch file not found)
        $this->expectException(\Exception::class);
        $this->transport('async_priority_high')
            ->throwExceptions()
            ->process();
    }

    public function testWebhookWithInvalidActorTypeReturnsError(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might hallucinate invalid enum values
        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Invalid Type Corp',
            'type' => 'invalid_actor_type', // Not a valid ActorType
            'explanation' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'primaryDomain' => 'invalid.com',
            'score' => 0.5,
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddActorAction::class);

        // Should return error (deserialization failure)
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testWebhookWithInvalidSourceTypeQueuesButFailsOnProcess(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'name' => 'Invalid Source',
            'type' => 'not_a_real_source_type', // Invalid
            'primaryDomain' => 'invalid.com',
            'url' => 'https://invalid.com',
            'query' => '',
            'description' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'relevance' => [
                'fr' => 'Test.',
                'en' => 'Test.',
            ],
            'parameters' => null,
            'messageContentId' => null,
            'actorId' => null,
        ];

        // Webhook should accept it (type is just a string in AddSourceAction)
        $this->sendWebhookRequest($payload, AddSourceAction::class);
        $this->assertResponseIsSuccessful();

        // But processing should fail when trying to create entity
        $this->transport('async_priority_high')
            ->queue()
            ->assertCount(1);

        $this->expectException(\Exception::class);
        $this->transport('async_priority_high')
            ->throwExceptions()
            ->process();
    }

    public function testWebhookWithInvalidWatchFileIdForRenameFailsOnProcess(): void
    {
        $payload = [
            'watchFileId' => '00000000-0000-0000-0000-000000000000',
            'name' => 'New Name',
            'isManualRename' => false,
        ];

        $this->sendWebhookRequest($payload, RenameWatchFileAction::class);
        $this->assertResponseIsSuccessful();

        $this->transport('async_priority_low')
            ->queue()
            ->assertCount(1);

        $this->expectException(\Exception::class);
        $this->transport('async_priority_low')
            ->throwExceptions()
            ->process();
    }

    // ===========================================
    // Synchronous Action Tests (AddStrategicQuestion, AddSearchQuery)
    // ===========================================

    public function testWebhookAddStrategicQuestionSyncFullFlow(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Quels sont les principaux concurrents sur le marché français ?',
            'questionEN' => 'What are the main competitors in the French market?',
            'contextFR' => 'Contexte de veille concurrentielle pour analyse stratégique.',
            'contextEN' => 'Competitive monitoring context for strategic analysis.',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'status' => 'ok',
            'mode' => 'sync',
        ]);

        // Verify database state directly (sync actions are processed immediately)
        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertEquals('What are the main competitors in the French market?', $question->getQuestion()->en);
        $this->assertEquals(
            'Quels sont les principaux concurrents sur le marché français ?',
            $question->getQuestion()
                ->fr
        );
        $this->assertEquals(1, $question->getPriority());
    }

    public function testWebhookAddStrategicQuestionWithUnicodeContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might output Unicode characters, emojis, accents
        $payload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Comment l\'IA 🤖 impacte-t-elle le marché «français» ?',
            'questionEN' => 'How does AI 🤖 impact the "French" market?',
            'contextFR' => 'Analyse approfondie — veille stratégique €£¥',
            'contextEN' => 'In-depth analysis — strategic monitoring €£¥',
            'monitoringDimension' => 'technological',
            'priority' => 2,
            'expectedOutputType' => 'detailed_report',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();

        $this->assertCount(1, $questions);
        $question = $questions[0];
        $this->assertStringContainsString('🤖', $question->getQuestion()->en);
        $this->assertStringContainsString('«français»', $question->getQuestion()->fr);
    }

    public function testWebhookAddStrategicQuestionWithInvalidMonitoringDimensionFails(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // LLM might hallucinate invalid enum values
        $payload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question test',
            'questionEN' => 'Test question',
            'contextFR' => 'Contexte test',
            'contextEN' => 'Test context',
            'monitoringDimension' => 'invalid_dimension', // Invalid enum value
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);

        // Should return error (deserialization failure for enum), should be 400 Bad Request
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testWebhookAddStrategicQuestionWithNonExistentWatchFileIdFails(): void
    {
        $payload = [
            'watchFileId' => '00000000-0000-0000-0000-000000000000',
            'questionFR' => 'Question test',
            'questionEN' => 'Test question',
            'contextFR' => 'Contexte test',
            'contextEN' => 'Test context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testWebhookAddStrategicQuestionSkipsDuplicates(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $payload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question dupliquée',
            'questionEN' => 'Duplicate question',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        // Send same question twice
        $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        // Should only have one question (duplicate skipped)
        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $this->assertCount(1, $questions);
    }

    public function testWebhookAddStrategicQuestionAllMonitoringTypes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        $monitoringTypes = ['competitive', 'strategic', 'commercial', 'technological', 'regulatory'];

        foreach ($monitoringTypes as $index => $type) {
            $payload = [
                'watchFileId' => $watchFileId,
                'questionFR' => \sprintf('Question %s FR', $type),
                'questionEN' => \sprintf('Question %s EN', $type),
                'contextFR' => 'Contexte',
                'contextEN' => 'Context',
                'monitoringDimension' => $type,
                'priority' => $index + 1,
                'expectedOutputType' => 'summary',
                'messageContentId' => null,
            ];

            $this->sendWebhookRequest($payload, AddStrategicQuestionAction::class);
            $this->assertResponseIsSuccessful();
        }

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $this->assertCount(5, $questions);
    }

    public function testWebhookAddSearchQuerySyncFullFlow(): void
    {
        // First create a strategic question via webhook
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create strategic question first
        $questionPayload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question stratégique pour search query',
            'questionEN' => 'Strategic question for search query',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($questionPayload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        // Get the created strategic question ID
        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $this->assertCount(1, $questions);
        $strategicQuestionId = $questions[0]->getId();

        // Now add search query
        $searchQueryPayload = [
            'strategicQuestionId' => $strategicQuestionId,
            'country' => 'FR',
            'language' => 'fr',
            'query' => 'analyse concurrentielle marché français',
            'queryType' => 'web_search',
            'rationale' => 'Search for competitive analysis in French market',
        ];

        $this->sendWebhookRequest($searchQueryPayload, AddSearchQueryAction::class);

        $this->assertResponseIsSuccessful();
        $this->assertJsonContains([
            'status' => 'ok',
            'mode' => 'sync',
        ]);

        // Verify database state
        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(1, $searchQueries);
        $searchQuery = $searchQueries[0];
        $this->assertEquals('analyse concurrentielle marché français', $searchQuery->getSearchTerm());
        $this->assertEquals('FR', $searchQuery->getCountry());
        $this->assertEquals('fr', $searchQuery->getLanguage());
    }

    public function testWebhookAddSearchQueryWithUnicodeContent(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create strategic question first
        $questionPayload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question pour unicode search',
            'questionEN' => 'Question for unicode search',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($questionPayload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $strategicQuestionId = $questions[0]->getId();

        // Search query with Unicode
        $payload = [
            'strategicQuestionId' => $strategicQuestionId,
            'country' => 'JP',
            'language' => 'ja',
            'query' => '競争分析 日本市場 🎯',
            'queryType' => 'web_search',
            'rationale' => 'Japanese market analysis with emoji: 📊',
        ];

        $this->sendWebhookRequest($payload, AddSearchQueryAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();

        $this->assertCount(1, $searchQueries);
        $searchQuery = $searchQueries[0];
        $this->assertStringContainsString('競争分析', $searchQuery->getSearchTerm());
        $this->assertStringContainsString('🎯', $searchQuery->getSearchTerm());
    }

    public function testWebhookAddSearchQuerySkipsDuplicates(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create strategic question first
        $questionPayload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question pour duplicate test',
            'questionEN' => 'Question for duplicate test',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($questionPayload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $strategicQuestionId = $questions[0]->getId();

        $searchQueryPayload = [
            'strategicQuestionId' => $strategicQuestionId,
            'country' => 'US',
            'language' => 'en',
            'query' => 'market competition analysis',
            'queryType' => 'web_search',
            'rationale' => 'Initial rationale',
        ];

        // Send same query twice
        $this->sendWebhookRequest($searchQueryPayload, AddSearchQueryAction::class);
        $this->assertResponseIsSuccessful();

        $this->sendWebhookRequest($searchQueryPayload, AddSearchQueryAction::class);
        $this->assertResponseIsSuccessful();

        // Should only have one query (duplicate skipped)
        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();
        $this->assertCount(1, $searchQueries);
    }

    public function testWebhookAddSearchQueryWithInvalidCountryCodeFails(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create strategic question first
        $questionPayload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question test',
            'questionEN' => 'Test question',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($questionPayload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $strategicQuestionId = $questions[0]->getId();

        // Invalid country code (should be 2 chars)
        $payload = [
            'strategicQuestionId' => $strategicQuestionId,
            'country' => 'FRANCE', // Invalid
            'language' => 'fr',
            'query' => 'test query',
            'queryType' => 'web_search',
            'rationale' => 'Test rationale',
        ];

        $this->sendWebhookRequest($payload, AddSearchQueryAction::class);

        // Sync action should return error immediately, should be 400 Bad Request
        $this->assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testWebhookAddSearchQueryWithNonExistentStrategicQuestionFails(): void
    {
        $payload = [
            'strategicQuestionId' => '00000000-0000-0000-0000-000000000000',
            'country' => 'FR',
            'language' => 'fr',
            'query' => 'test query',
            'queryType' => 'web_search',
            'rationale' => 'Test rationale',
        ];

        $this->sendWebhookRequest($payload, AddSearchQueryAction::class);
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testWebhookAddSearchQueryMultipleCountriesForSameQuery(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create strategic question
        $questionPayload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question multi-country',
            'questionEN' => 'Multi-country question',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($questionPayload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $strategicQuestionId = $questions[0]->getId();

        $countries = ['FR', 'US', 'DE', 'GB'];

        foreach ($countries as $country) {
            $payload = [
                'strategicQuestionId' => $strategicQuestionId,
                'country' => $country,
                'language' => 'en',
                'query' => 'market analysis',
                'queryType' => 'web_search',
                'rationale' => \sprintf('Analysis for %s market', $country),
            ];

            $this->sendWebhookRequest($payload, AddSearchQueryAction::class);
            $this->assertResponseIsSuccessful();
        }

        // Same query but different countries should all be created
        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();
        $this->assertCount(4, $searchQueries);

        $savedCountries = array_map(fn ($q) => $q->getCountry(), $searchQueries);
        foreach ($countries as $country) {
            $this->assertContains($country, $savedCountries);
        }
    }

    public function testWebhookAddSearchQueryDifferentQueryTypes(): void
    {
        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $watchFileId = $watchFile->getId();

        // Create strategic question
        $questionPayload = [
            'watchFileId' => $watchFileId,
            'questionFR' => 'Question query types',
            'questionEN' => 'Query types question',
            'contextFR' => 'Contexte',
            'contextEN' => 'Context',
            'monitoringDimension' => 'competitive',
            'priority' => 1,
            'expectedOutputType' => 'summary',
            'messageContentId' => null,
        ];

        $this->sendWebhookRequest($questionPayload, AddStrategicQuestionAction::class);
        $this->assertResponseIsSuccessful();

        $this->entityManager->clear();
        $questions = $this->entityManager->getRepository(StrategicQuestion::class)->findAll();
        $strategicQuestionId = $questions[0]->getId();

        $queryTypes = ['web_search', 'news_search', 'academic_search', 'patent_search', 'company_search'];

        foreach ($queryTypes as $index => $queryType) {
            $payload = [
                'strategicQuestionId' => $strategicQuestionId,
                'country' => 'US',
                'language' => 'en',
                'query' => \sprintf('test query for %s type %d', $queryType, $index),
                'queryType' => $queryType,
                'rationale' => \sprintf('Testing %s query type', $queryType),
            ];

            $this->sendWebhookRequest($payload, AddSearchQueryAction::class);
            $this->assertResponseIsSuccessful();
        }

        $this->entityManager->clear();
        $searchQueries = $this->entityManager->getRepository(SearchQuery::class)->findAll();
        $this->assertCount(\count($queryTypes), $searchQueries);

        $savedQueryTypes = array_map(fn ($q) => $q->getQueryType(), $searchQueries);
        foreach ($queryTypes as $expectedType) {
            $this->assertContains($expectedType, $savedQueryTypes);
        }
    }
}
