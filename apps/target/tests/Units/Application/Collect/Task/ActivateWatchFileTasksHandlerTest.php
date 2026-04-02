<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Collect\Task;

use App\Application\Collect\Task\ActivateWatchFileTasksAction;
use App\Application\Collect\Task\ActivateWatchFileTasksHandler;
use App\Application\Collect\Task\CreateCollectTaskAction;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ActivateWatchFileTasksHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullMessageBus $messageBus;
    private ActivateWatchFileTasksHandler $handler;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->messageBus = new NullMessageBus();

        $this->handler = new ActivateWatchFileTasksHandler($this->messageBus, new NullLogger());
        $this->handler->setWatchFileGateway($this->watchFileGateway);
    }

    public function testInvokeDispatchesActionsForActiveSources(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'wf-1');
        $watchFile->setStatus(WatchFileStatus::ENABLED);

        $source1 = new Source('Source 1', new TranslatedText(
            'Description en FR',
            'Description in EN'
        ), SourceType::WEBSITE, 'url1', 'domain1', new TranslatedText('Pertinence FR', 'Relevancy EN'), null);
        $this->forcePropertyValue($source1, 's-1');
        $source1->activate();
        $watchFile->addSource($source1);

        $source2 = new Source('Source 2', new TranslatedText(
            'Description en FR',
            'Description in EN'
        ), SourceType::WEBSITE, 'url2', 'domain2', new TranslatedText('Pertinence FR', 'Relevancy EN'), null);
        $this->forcePropertyValue($source2, 's-2');
        $source2->activate();
        $watchFile->addSource($source2);

        $this->watchFileGateway->save($watchFile);

        ($this->handler)(new ActivateWatchFileTasksAction('wf-1'));

        $dispatchedMessages = $this->messageBus->getDispatchedMessages();
        $this->assertCount(2, $dispatchedMessages);
        $this->assertInstanceOf(CreateCollectTaskAction::class, $dispatchedMessages[0]);
        $this->assertSame('s-1', $dispatchedMessages[0]->sourceId);
        $this->assertInstanceOf(CreateCollectTaskAction::class, $dispatchedMessages[1]);
        $this->assertSame('s-2', $dispatchedMessages[1]->sourceId);
    }

    public function testInvokeDoesNothingForInactiveWatchFile(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'wf-1');
        $watchFile->setStatus(WatchFileStatus::DRAFT); // Inactive
        $this->watchFileGateway->save($watchFile);

        ($this->handler)(new ActivateWatchFileTasksAction('wf-1'));

        $this->assertEmpty($this->messageBus->getDispatchedMessages());
    }

    public function testInvokeSkipsInactiveSources(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'wf-1');
        $watchFile->setStatus(WatchFileStatus::ENABLED);

        $source1 = new Source('Source 1', new TranslatedText(
            'Description en FR',
            'Description in EN'
        ), SourceType::WEBSITE, 'url1', 'domain1', new TranslatedText('Pertinence FR', 'Relevancy EN'), null);
        $this->forcePropertyValue($source1, 's-1');
        $source1->activate();
        $watchFile->addSource($source1);

        $source2 = new Source('Source 2', new TranslatedText(
            'Description en FR',
            'Description in EN'
        ), SourceType::WEBSITE, 'url2', 'domain2', new TranslatedText('Pertinence FR', 'Relevancy EN'), null);
        $this->forcePropertyValue($source2, 's-2');
        $source2->deactivate(); // Inactive
        $watchFile->addSource($source2);

        $this->watchFileGateway->save($watchFile);

        ($this->handler)(new ActivateWatchFileTasksAction('wf-1'));

        $dispatchedMessages = $this->messageBus->getDispatchedMessages();
        $this->assertCount(1, $dispatchedMessages);
        $this->assertInstanceOf(CreateCollectTaskAction::class, $dispatchedMessages[0]);
        $this->assertSame('s-1', $dispatchedMessages[0]->sourceId);
    }
}
