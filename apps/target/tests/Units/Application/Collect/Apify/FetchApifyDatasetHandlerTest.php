<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Apify;

use App\Application\Collect\Apify\FetchApifyDatasetAction;
use App\Application\Collect\Apify\FetchApifyDatasetHandler;
use App\Application\Document\AddDocumentAction;
use App\Domain\Collect\ApifyDocumentNormalizerInterface;
use App\Domain\Collect\ApifyNormalizerResolverInterface;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\ApifyDatasetNotFoundException;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Collect\NormalizerContext;
use App\Domain\Document\Document;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Apify\Normalizer\ApifyNormalizerResolver;
use App\Infrastructure\Collect\Apify\Normalizer\GenericApifyNormalizer;
use App\Tests\Units\Infrastructure\Collect\Apify\NullApifyHttpClient;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Units\Infrastructure\SourceActivity\NullSourceActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

#[CoversClass(FetchApifyDatasetHandler::class)]
class FetchApifyDatasetHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private const string TASK_ID = 'test-collect-task-id';
    private NullApifyHttpClient $apifyClient;
    private NullCollectTaskGateway $collectTaskGateway;

    /** @var EventDispatcherInterface&Stub */
    private EventDispatcherInterface $eventDispatcher;
    private NullSourceActivityLogger $sourceActivityLogger;
    private NullMessageBus $messageBus;
    private ApifyNormalizerResolverInterface $normalizerResolver;
    private FetchApifyDatasetHandler $handler;

    protected function setUp(): void
    {
        $this->apifyClient = new NullApifyHttpClient();
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $this->sourceActivityLogger = new NullSourceActivityLogger();
        $this->messageBus = new NullMessageBus();
        $this->normalizerResolver = new ApifyNormalizerResolver(new \ArrayObject([]), new GenericApifyNormalizer());

        $this->handler = new FetchApifyDatasetHandler(
            $this->apifyClient,
            $this->collectTaskGateway,
            $this->eventDispatcher,
            $this->sourceActivityLogger,
            $this->normalizerResolver,
            $this->messageBus,
            new NullLogger()
        );
    }

    public function testSuccessfullyFetchesDatasetItems(): void
    {
        $datasetId = 'dataset_123';
        $testItems = [
            [
                'id' => 'item1',
                'title' => 'Item 1',
                'url' => 'https://example.com/1',
                'text' => 'Content of item 1 for testing',
            ],
            [
                'id' => 'item2',
                'title' => 'Item 2',
                'url' => 'https://example.com/2',
                'text' => 'Content of item 2 for testing',
            ],
            [
                'id' => 'item3',
                'title' => 'Item 3',
                'url' => 'https://example.com/3',
                'text' => 'Content of item 3 for testing',
            ],
        ];

        $this->createAndSaveCollectTask();
        $this->apifyClient->addResponse('/datasets/' . $datasetId . '/items', $testItems);
        $this->apifyClient->addResponse('/datasets/' . $datasetId, [
            'data' => [
                'itemCount' => \count($testItems),
                'id' => $datasetId,
            ],
        ]);

        ($this->handler)(new FetchApifyDatasetAction(self::TASK_ID, $datasetId));

        $saved = $this->collectTaskGateway->get(self::TASK_ID);
        $this->assertEquals(CollectTaskStatus::COMPLETED, $saved->getStatus());
        $this->assertSame(3, $this->messageBus->countDispatched(AddDocumentAction::class));

        $configuration = $saved->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertTrue($result['success']);
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertSame(3, $metadata['documentsCreated']);
    }

    public function testFetchesLargeDatasetWithPagination(): void
    {
        $datasetId = 'dataset_large';
        $testItems = [];
        for ($i = 1; $i <= 250; ++$i) {
            $testItems[] = [
                'id' => 'item' . $i,
                'title' => 'Item ' . $i,
                'url' => 'https://example.com/item/' . $i,
                'text' => 'Content of item ' . $i . ' for testing purposes with enough text',
            ];
        }

        $this->createAndSaveCollectTask();
        $this->apifyClient->addResponse('/datasets/' . $datasetId, [
            'data' => [
                'itemCount' => \count($testItems),
                'id' => $datasetId,
            ],
        ]);
        $this->apifyClient->addResponse(
            '/datasets/' . $datasetId . '/items',
            function (string $method, string $path, array $options) use ($testItems): array {
                $offset = (int) ($options['query']['offset'] ?? 0);
                $limit = (int) ($options['query']['limit'] ?? 100);

                return \array_slice($testItems, $offset, $limit);
            }
        );

        ($this->handler)(new FetchApifyDatasetAction(self::TASK_ID, $datasetId));

        $this->assertSame(250, $this->messageBus->countDispatched(AddDocumentAction::class));

        $saved = $this->collectTaskGateway->get(self::TASK_ID);
        $configuration = $saved->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertTrue($result['success']);
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertSame(250, $metadata['documentsCreated']);
    }

    public function testFailsWhenDatasetMetadataNotFound(): void
    {
        $this->createAndSaveCollectTask();
        $this->apifyClient->throwOnRequest(ApifyDatasetNotFoundException::withId('dataset_not_found'));

        try {
            ($this->handler)(new FetchApifyDatasetAction(self::TASK_ID, 'dataset_not_found'));
            $this->fail('Expected CollectException to be thrown');
        } catch (CollectException $e) {
            $this->assertMatchesRegularExpression('/not found or metadata unavailable/', $e->getMessage());
        }

        $saved = $this->collectTaskGateway->get(self::TASK_ID);
        $this->assertSame(CollectTaskStatus::FAILED, $saved->getStatus());

        $configuration = $saved->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['error_message']);
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertSame('dataset_not_found', $metadata['datasetId']);
        $this->assertArrayHasKey('failedAt', $metadata);
    }

    public function testStoresResultMetadata(): void
    {
        $datasetId = 'dataset_meta';
        $testItems = [[
            'id' => 'item1',
            'data' => 'test',
        ]];

        $this->createAndSaveCollectTask();
        $this->apifyClient->addResponse('/datasets/' . $datasetId, [
            'data' => [
                'itemCount' => \count($testItems),
                'id' => $datasetId,
            ],
        ]);
        $this->apifyClient->addResponse('/datasets/' . $datasetId . '/items', $testItems);

        ($this->handler)(new FetchApifyDatasetAction(self::TASK_ID, $datasetId));

        $saved = $this->collectTaskGateway->get(self::TASK_ID);
        $configuration = $saved->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        $this->assertArrayHasKey('metadata', $result);
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertSame(1, $metadata['itemCount']);
        $this->assertSame($datasetId, $metadata['datasetId']);
        $this->assertArrayHasKey('fetchedAt', $metadata);
        $this->assertArrayHasKey('documentsCreated', $metadata);
        $this->assertSame(1, $metadata['documentsCreated']);
    }

    public function testLogsCollectTaskNotFound(): void
    {
        $this->expectException(CollectException::class);

        ($this->handler)(new FetchApifyDatasetAction('unknown-id', 'dataset_123'));
    }

    public function testSkipsItemsWhenNormalizerReturnsNull(): void
    {
        $datasetId = 'dataset_null_normalizer';
        $testItems = [
            [
                'id' => 'item1',
                'title' => 'Item 1',
                'url' => 'https://example.com/1',
                'text' => 'Content 1',
            ],
            [
                'id' => 'item2',
                'title' => 'Item 2',
                'url' => 'https://example.com/2',
                'text' => 'Content 2',
            ],
        ];

        $nullNormalizer = new class implements ApifyDocumentNormalizerInterface {
            public function supports(string $apifyActorId): bool
            {
                return true;
            }

            /** @param array<string, mixed> $item */
            public function normalize(array $item, NormalizerContext $context): ?Document
            {
                return null;
            }
        };

        $resolver = new class($nullNormalizer) implements ApifyNormalizerResolverInterface {
            public function __construct(
                private readonly ApifyDocumentNormalizerInterface $normalizer,
            ) {
            }

            public function resolveFor(CollectTask $collectTask): ApifyDocumentNormalizerInterface
            {
                return $this->normalizer;
            }
        };

        $handler = new FetchApifyDatasetHandler(
            $this->apifyClient,
            $this->collectTaskGateway,
            $this->eventDispatcher,
            $this->sourceActivityLogger,
            $resolver,
            $this->messageBus,
            new NullLogger()
        );

        $this->createAndSaveCollectTask();
        $this->apifyClient->addResponse('/datasets/' . $datasetId, [
            'data' => [
                'itemCount' => \count($testItems),
                'id' => $datasetId,
            ],
        ]);
        $this->apifyClient->addResponse('/datasets/' . $datasetId . '/items', $testItems);

        ($handler)(new FetchApifyDatasetAction(self::TASK_ID, $datasetId));

        $this->assertSame(0, $this->messageBus->countDispatched(AddDocumentAction::class));

        $saved = $this->collectTaskGateway->get(self::TASK_ID);
        $this->assertEquals(CollectTaskStatus::COMPLETED, $saved->getStatus());
        $configuration = $saved->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertSame(0, $metadata['documentsCreated']);
    }

    public function testDocumentProviderIdIsPrefixedWithActorType(): void
    {
        $datasetId = 'dataset_providerid';
        $apifyActorId = 'lhotanova/google-news-scraper';
        $url = 'https://example.com/article';
        $expectedProviderId = \sprintf('apify:%s:%s', $apifyActorId, $url);

        $this->createAndSaveCollectTask(\sprintf('%s:run_abc123', $apifyActorId));
        $this->apifyClient->addResponse('/datasets/' . $datasetId, [
            'data' => [
                'itemCount' => 1,
                'id' => $datasetId,
            ],
        ]);
        $this->apifyClient->addResponse('/datasets/' . $datasetId . '/items', [[
            'url' => $url,
            'title' => 'Article Title',
            'text' => 'Long enough content to pass the excerpt minimum length check',
        ]]);

        ($this->handler)(new FetchApifyDatasetAction(self::TASK_ID, $datasetId));

        $dispatched = $this->messageBus->getFirstDispatched(AddDocumentAction::class);
        $this->assertNotNull($dispatched);
        $this->assertSame($expectedProviderId, $dispatched->document->getProviderId());
    }

    public function testHandlesEmptyDataset(): void
    {
        $datasetId = 'dataset_empty';

        $this->createAndSaveCollectTask();
        $this->apifyClient->addResponse('/datasets/' . $datasetId, [
            'data' => [
                'itemCount' => 0,
                'id' => $datasetId,
            ],
        ]);
        $this->apifyClient->addResponse('/datasets/' . $datasetId . '/items', []);

        ($this->handler)(new FetchApifyDatasetAction(self::TASK_ID, $datasetId));

        $this->assertSame(0, $this->messageBus->countDispatched(AddDocumentAction::class));

        $saved = $this->collectTaskGateway->get(self::TASK_ID);
        $this->assertEquals(CollectTaskStatus::COMPLETED, $saved->getStatus());
        $configuration = $saved->getConfiguration();
        /** @var array<string, mixed> $result */
        $result = $configuration['result'];
        /** @var array<string, mixed> $metadata */
        $metadata = $result['metadata'];
        $this->assertSame(0, $metadata['documentsCreated']);
    }

    private function createAndSaveCollectTask(string $providerTaskId = 'apify_run_id'): CollectTask
    {
        $organisation = new Organisation('Test Org', 'test-org-id');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', $organisation);
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            null,
            $watchFile
        );

        $task = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'apify',
            configuration: [],
            providerTaskId: $providerTaskId
        );

        $this->forcePropertyValue($task, self::TASK_ID);
        $task->start($providerTaskId, $this->eventDispatcher);
        $this->forcePropertyValue($task, CollectTaskStatus::RUNNING, 'status');
        $this->collectTaskGateway->save($task);

        return $task;
    }
}
