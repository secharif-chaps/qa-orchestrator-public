<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Apify;

use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Infrastructure\Collect\Apify\ApifyCollectTaskMapper;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(ApifyCollectTaskMapper::class)]
class ApifyCollectTaskMapperTest extends TestCase
{
    /**
     * @param array<string, string> $actorMapping
     */
    private function createMapper(
        string $webhookBaseUrl = 'https://example.com',
        string $maxTotalChargeUsd = '0.10',
        array $actorMapping = [],
        ?MessageBusInterface $messageBus = null,
        ?LoggerInterface $logger = null,
    ): ApifyCollectTaskMapper {
        $messageBus ??= new NullMessageBus();

        return new ApifyCollectTaskMapper(
            $webhookBaseUrl,
            $maxTotalChargeUsd,
            $actorMapping,
            $messageBus,
            $logger,
        );
    }

    private function createMessageBusWithToken(string $token): NullMessageBus
    {
        return new NullMessageBus(fakeHandler: fn () => $token);
    }

    /**
     * @param array<string, mixed>|null $parameters
     */
    private function createCollectTask(
        string $taskId = 'task-uuid-123',
        SourceType $sourceType = SourceType::RSS_FEED,
        string $url = 'https://example.com/feed.xml',
        ?array $parameters = null,
    ): CollectTask {
        $source = $this->createStub(Source::class);
        $source->method('getType')
->willReturn($sourceType);
        $source->method('getUrl')
->willReturn($url);
        $source->method('getParameters')
->willReturn($parameters);

        $collectTask = $this->createStub(CollectTask::class);
        $collectTask->method('getId')
->willReturn($taskId);
        $collectTask->method('getSource')
->willReturn($source);

        return $collectTask;
    }

    public function testMapResolvesActorId(): void
    {
        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask();
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertSame('apify/rss-scraper', $config->actorId);
    }

    public function testMapUnknownSourceTypeThrows(): void
    {
        $this->expectException(NotSupportedCollectorException::class);
        $this->expectExceptionMessage('No Apify actor configured for source type: rss_feed');

        $mapper = $this->createMapper(actorMapping: []);
        $collectTask = $this->createCollectTask();

        $mapper->mapToActorRun($collectTask);
    }

    public function testMapBuildsWebhooksBase64(): void
    {
        $messageBus = $this->createMessageBusWithToken('test-jwt-token');
        $mapper = $this->createMapper(
            webhookBaseUrl: 'https://target.example.com',
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask();
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertArrayHasKey('webhooks', $config->queryParams);
        $webhooks = $config->queryParams['webhooks'];
        $this->assertIsString($webhooks);

        $decoded = json_decode(base64_decode($webhooks), true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertIsArray($decoded[0]);
        $this->assertSame([
            'ACTOR.RUN.SUCCEEDED',
            'ACTOR.RUN.FAILED',
            'ACTOR.RUN.TIMED_OUT',
            'ACTOR.RUN.ABORTED',
        ], $decoded[0]['eventTypes']);
        $this->assertIsString($decoded[0]['requestUrl']);
        $this->assertStringContainsString(
            'https://target.example.com/api/apify/webhook?token=test-jwt-token',
            $decoded[0]['requestUrl']
        );
    }

    public function testMapIncludesMaxCharge(): void
    {
        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            maxTotalChargeUsd: '0.50',
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask();
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertArrayHasKey('maxTotalChargeUsd', $config->queryParams);
        $this->assertSame('0.50', $config->queryParams['maxTotalChargeUsd']);
    }

    public function testMapIncludesSourceUrl(): void
    {
        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask(url: 'https://blog.example.com/feed');
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertSame('https://blog.example.com/feed', $config->input['url']);
    }

    public function testMapMergesSourceParameters(): void
    {
        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask(parameters: [
            'depth' => 2,
            'language' => 'en',
        ]);
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertSame('https://example.com/feed.xml', $config->input['url']);
        $this->assertSame(2, $config->input['depth']);
        $this->assertSame('en', $config->input['language']);
    }

    public function testMapOmitsWebhooksWhenBaseUrlEmpty(): void
    {
        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            webhookBaseUrl: '',
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask();
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertArrayNotHasKey('webhooks', $config->queryParams);
    }

    public function testMapOmitsMaxChargeWhenEmpty(): void
    {
        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            maxTotalChargeUsd: '',
            actorMapping: [
                'rss_feed' => 'apify/rss-scraper',
            ],
            messageBus: $messageBus,
        );

        $collectTask = $this->createCollectTask();
        $config = $mapper->mapToActorRun($collectTask);

        $this->assertArrayNotHasKey('maxTotalChargeUsd', $config->queryParams);
    }

    public function testGetActorMapping(): void
    {
        $mapping = [
            'rss_feed' => 'apify/rss',
            'blog' => 'apify/blog',
        ];
        $mapper = $this->createMapper(actorMapping: $mapping);

        $this->assertSame($mapping, $mapper->getActorMapping());
    }

    public function testMapNormalizesActorIdContainingSeparator(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('normalizing for providerTaskId encoding'),
                $this->callback(function (array $context): bool {
                    $this->assertSame('foo:bar', $context['actor_id_raw']);
                    $this->assertSame('foo_bar', $context['actor_id_normalized']);

                    return true;
                }),
            );

        $messageBus = $this->createMessageBusWithToken('jwt-token');
        $mapper = $this->createMapper(
            actorMapping: [
                'rss_feed' => 'foo:bar',
            ],
            messageBus: $messageBus,
            logger: $logger,
        );

        $collectTask = $this->createCollectTask();
        $config = $mapper->mapToActorRun($collectTask);

        // ":" is reserved as providerTaskId separator and must not appear in actorId
        $this->assertSame('foo_bar', $config->actorId);
    }
}
