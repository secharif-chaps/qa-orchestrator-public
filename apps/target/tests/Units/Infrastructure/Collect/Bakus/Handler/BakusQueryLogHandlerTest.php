<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Bakus\Handler;

use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Bakus\Handler\BakusQueryLogHandler;
use App\Tests\Units\Infrastructure\Collect\NullCollectTaskGateway;
use App\Tests\Units\Infrastructure\SourceActivity\NullSourceActivityGateway;
use App\Tests\Units\Infrastructure\SourceActivity\NullSourceActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BakusQueryLogHandler::class)]
class BakusQueryLogHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private BakusQueryLogHandler $handler;
    private NullCollectTaskGateway $collectTaskGateway;
    private NullSourceActivityLogger $sourceActivityLogger;
    private NullSourceActivityGateway $sourceActivityGateway;

    protected function setUp(): void
    {
        $this->collectTaskGateway = new NullCollectTaskGateway();
        $this->sourceActivityLogger = new NullSourceActivityLogger();
        $this->sourceActivityGateway = new NullSourceActivityGateway();

        $this->handler = new BakusQueryLogHandler(
            $this->collectTaskGateway,
            $this->sourceActivityLogger,
            $this->sourceActivityGateway,
        );
    }

    public function testSupportsWithQueryLogType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_log',
            ],
        );

        $this->assertTrue($this->handler->supports($event));
    }

    public function testSupportsWithDifferentType(): void
    {
        $event = new CollectDataReceivedEvent(
            'collect-task-123',
            'provider-task-456',
            [
                'type' => 'query_status',
            ],
        );

        $this->assertFalse($this->handler->supports($event));
    }

    public function testSupportsWithMissingType(): void
    {
        $event = new CollectDataReceivedEvent('collect-task-123', 'provider-task-456', []);

        $this->assertFalse($this->handler->supports($event));
    }

    public function testInvokeCreatesSourceActivityWithCollectLogType(): void
    {
        $collectTask = $this->createCollectTask();
        $this->collectTaskGateway->save($collectTask);

        $queryLogData = [
            'type' => 'query_log',
            'query_id' => 'query-789',
            'collection_id' => 'col-001',
            'raw_id' => 'raw-123',
            'result' => [
                'status' => 'success',
                'count' => 5,
            ],
            'collector_module_name' => 'rss_collector',
            'collector_module_version' => '1.2.0',
            'callback_type' => 'websocket_pull',
        ];

        $event = new CollectDataReceivedEvent('test-task-id', 'provider-task-456', $queryLogData);

        ($this->handler)($event);

        $activities = $this->sourceActivityGateway->getAll();
        $this->assertCount(1, $activities);
        $this->assertSame(SourceActivityActionType::SOURCE_QUERY_LOG, $activities[0]->getActionType());
    }

    public function testInvokeIncludesProviderNameInActionData(): void
    {
        $collectTask = $this->createCollectTask();
        $this->collectTaskGateway->save($collectTask);

        $event = new CollectDataReceivedEvent(
            'test-task-id',
            'provider-task-456',
            [
                'type' => 'query_log',
                'query_id' => 'query-789',
            ],
        );

        ($this->handler)($event);

        $activities = $this->sourceActivityGateway->getAll();
        $this->assertCount(1, $activities);

        $actionData = $activities[0]->getActionData();
        $this->assertSame('bakus', $actionData['provider_name']);
        $this->assertSame('query_log', $actionData['type']);
        $this->assertSame('query-789', $actionData['query_id']);
    }

    public function testInvokeWithNonExistentCollectTask(): void
    {
        $event = new CollectDataReceivedEvent(
            'non-existent-id',
            'provider-task-456',
            [
                'type' => 'query_log',
            ],
        );

        ($this->handler)($event);

        $this->assertCount(0, $this->sourceActivityGateway->getAll());
    }

    private function createCollectTask(): CollectTask
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'desc fr',
                'en' => 'desc en',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel fr',
                'en' => 'rel en',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'test-source-id');

        $collectTask = new CollectTask(source: $source, watchFile: $watchFile, providerName: 'bakus');
        $this->forcePropertyValue($collectTask, 'test-task-id');

        return $collectTask;
    }
}
