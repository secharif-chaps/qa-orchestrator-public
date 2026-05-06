<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Source;

use App\Application\WatchFile\Source\AddMultipleSourcesAction;
use App\Application\WatchFile\Source\AddMultipleSourcesHandler;
use App\Application\WatchFile\Source\AddSourceAction;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class AddMultipleSourcesHandlerTest extends TestCase
{
    private MessageBusInterface&MockObject $messageBus;
    private WatchFileGatewayInterface $watchFileGateway;
    private LoggerInterface&MockObject $logger;
    private AddMultipleSourcesHandler $handler;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new AddMultipleSourcesHandler($this->messageBus, $this->logger);

        // Use reflection to set the private property
        $reflection = new \ReflectionClass($this->handler);
        $property = $reflection->getProperty('watchFileGateway');
        $property->setAccessible(true);
        $property->setValue($this->handler, $this->watchFileGateway);
    }

    public function testInvokeWithMultipleSources(): void
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $sources = [
            new AddSourceAction(
                watchFileId: 'original_1',
                name: 'Source 1',
                type: 'website',
                primaryDomain: 'example1.com',
                url: 'https://example1.com',
                query: 'query 1',
                description: new TranslatedText('Description 1 FR', 'Description 1 EN'),
                relevance: new TranslatedText('Relevance 1 FR', 'Relevance 1 EN'),
            ),
            new AddSourceAction(
                watchFileId: 'original_2',
                name: 'Source 2',
                type: 'rss',
                primaryDomain: 'example2.com',
                url: 'https://example2.com',
                query: 'query 2',
                description: new TranslatedText('Description 2 FR', 'Description 2 EN'),
                relevance: new TranslatedText('Relevance 2 FR', 'Relevance 2 EN'),
            ),
        ];

        $action = new AddMultipleSourcesAction(
            watchFileId: $watchFile->getId(),
            sources: $sources,
            messageContentId: 'common_message',
        );

        $this->messageBus
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                $this->assertInstanceOf(AddSourceAction::class, $message);

                return new Envelope($message);
            });

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = ($this->handler)($action);

        $this->assertSame($watchFile, $result);
    }

    public function testInvokeWithEmptySources(): void
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $action = new AddMultipleSourcesAction(watchFileId: $watchFile->getId(), sources: []);

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = ($this->handler)($action);

        $this->assertSame($watchFile, $result);
    }

    public function testInvokeWithSingleSource(): void
    {
        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->watchFileGateway->save($watchFile);

        $sources = [
            new AddSourceAction(
                watchFileId: 'original_1',
                name: 'Source 1',
                type: 'website',
                primaryDomain: 'example1.com',
                url: 'https://example1.com',
                query: 'query 1',
                description: new TranslatedText('Description 1 FR', 'Description 1 EN'),
                relevance: new TranslatedText('Relevance 1 FR', 'Relevance 1 EN'),
            ),
        ];

        $action = new AddMultipleSourcesAction(watchFileId: $watchFile->getId(), sources: $sources);

        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) {
                $this->assertInstanceOf(AddSourceAction::class, $message);

                return new Envelope($message);
            });

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = ($this->handler)($action);

        $this->assertSame($watchFile, $result);
    }
}
