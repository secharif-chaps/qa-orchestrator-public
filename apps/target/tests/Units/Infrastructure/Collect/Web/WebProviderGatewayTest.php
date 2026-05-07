<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Collect\Web;

use App\Application\Collect\Web\FetchWebUrlAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Collect\Exception\CollectException;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Collect\Web\WebProviderGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AllowMockObjectsWithoutExpectations]
#[CoversClass(WebProviderGateway::class)]
class WebProviderGatewayTest extends TestCase
{
    use EntityUtilsTrait;
    private NullMessageBus $messageBus;
    private WebProviderGateway $gateway;

    protected function setUp(): void
    {
        $this->messageBus = new NullMessageBus();
        $this->gateway = new WebProviderGateway($this->messageBus, new NullLogger());
    }

    public function testCreateTaskDispatchesFetchWebUrlActionWhenUrlInConfiguration(): void
    {
        $task = $this->createCollectTask([
            'url' => 'https://example.com/article',
        ]);

        $providerTaskId = $this->gateway->createTask($task);

        self::assertStringStartsWith('web-', $providerTaskId);
        self::assertCount(1, $this->messageBus->getDispatchedMessages());
        $dispatched = $this->messageBus->getDispatchedMessages()[0];
        self::assertInstanceOf(FetchWebUrlAction::class, $dispatched);
        self::assertSame('collect-task-web-1', $dispatched->collectTaskId);
        self::assertFalse($dispatched->sync);
    }

    public function testCreateTaskDispatchesFetchWebUrlActionWhenRawHtmlInConfiguration(): void
    {
        $task = $this->createCollectTask([
            'raw_html' => '<html><body>pasted</body></html>',
        ]);

        $providerTaskId = $this->gateway->createTask($task);

        self::assertStringStartsWith('web-', $providerTaskId);
        self::assertTrue($this->messageBus->hasDispatched(FetchWebUrlAction::class));
    }

    public function testCreateTaskForwardsSyncChainFlagFromConfiguration(): void
    {
        $task = $this->createCollectTask([
            'url' => 'https://example.com/sync',
            '_sync_chain' => true,
        ]);

        $this->gateway->createTask($task);

        $dispatched = $this->messageBus->getDispatchedMessages()[0];
        self::assertInstanceOf(FetchWebUrlAction::class, $dispatched);
        self::assertTrue($dispatched->sync, 'FetchWebUrlAction must inherit sync=true from CollectTask config');
    }

    public function testCreateTaskAddsDispatchAfterCurrentBusStamp(): void
    {
        $stampsCapture = null;
        $bus = new NullMessageBus(function (object $message, array $stamps) use (&$stampsCapture): mixed {
            $stampsCapture = $stamps;

            return null;
        });
        $gateway = new WebProviderGateway($bus, new NullLogger());

        $gateway->createTask($this->createCollectTask([
            'url' => 'https://example.com/article',
        ]));

        self::assertNotNull($stampsCapture);
        $hasDeferStamp = false;
        foreach ($stampsCapture as $stamp) {
            if ($stamp instanceof DispatchAfterCurrentBusStamp) {
                $hasDeferStamp = true;
                break;
            }
        }
        self::assertTrue(
            $hasDeferStamp,
            'FetchWebUrlAction must be deferred so the parent CreateCollectTaskHandler finishes its state machine first',
        );
    }

    public function testCreateTaskThrowsWhenNeitherUrlNorRawHtmlProvided(): void
    {
        $task = $this->createCollectTask([
            'title' => 'Just an override',
        ]);

        $this->expectException(CollectException::class);
        $this->expectExceptionMessageMatches('/url.*raw_html|raw_html.*url/');

        $this->gateway->createTask($task);
    }

    public function testCreateTaskThrowsWhenUrlIsEmptyString(): void
    {
        $task = $this->createCollectTask([
            'url' => '',
        ]);

        $this->expectException(CollectException::class);

        $this->gateway->createTask($task);
    }

    public function testGetTaskStatusReturnsQueuedStatus(): void
    {
        // The gateway has no remote API to poll — it returns a status that
        // matches what CreateCollectTaskHandler has just set via start(),
        // so the handler's "diff and update" branch does not fire.
        self::assertSame(CollectTaskStatus::QUEUED, $this->gateway->getTaskStatus('web-anything'));
    }

    public function testCancelTaskIsNoOp(): void
    {
        // Should not throw; nothing to assert beyond the absence of an
        // exception — there is no remote handle to abort.
        $this->gateway->cancelTask('web-some-id');
        $this->expectNotToPerformAssertions();
    }

    public function testGetCollectorsExposesManualSourceTypeOnly(): void
    {
        $collectors = $this->gateway->getCollectors();

        self::assertCount(1, $collectors);
        self::assertSame('web', $collectors[0]->name);
        self::assertContains(SourceType::MANUAL, $collectors[0]->supportedSourceTypes);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function createCollectTask(array $configuration): CollectTask
    {
        $organisation = new Organisation('Test Org', 'org-1');
        $watchFile = new WatchFile('Test Watch', 'objective', $organisation);
        $this->forcePropertyValue($watchFile, 'wf-1');
        $source = new Source(
            name: 'Manual Source',
            description: new TranslatedText('Description', 'Description'),
            type: SourceType::MANUAL,
            url: 'manual://wf-1',
            primaryDomain: 'manual',
            relevance: new TranslatedText('Relevance', 'Relevance'),
            actor: null,
            watchFile: $watchFile,
        );
        $this->forcePropertyValue($source, 'src-1');

        $task = new CollectTask(
            source: $source,
            watchFile: $watchFile,
            providerName: 'web',
            configuration: $configuration,
        );
        $this->forcePropertyValue($task, 'collect-task-web-1');

        return $task;
    }
}
