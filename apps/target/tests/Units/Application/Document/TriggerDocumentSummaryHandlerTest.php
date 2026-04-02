<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Agent\DocumentSummaryTriggerAgent;
use App\Application\Document\TriggerDocumentSummaryAction;
use App\Application\Document\TriggerDocumentSummaryHandler;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\SummaryStatus;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(TriggerDocumentSummaryHandler::class)]
class TriggerDocumentSummaryHandlerTest extends TestCase
{
    private TriggerDocumentSummaryHandler $handler;
    private NullDocumentGateway $documentGateway;
    private NullMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $this->messageBus = new NullMessageBus();
        $this->handler = new TriggerDocumentSummaryHandler(
            $this->documentGateway,
            $this->messageBus,
            new NullLogger()
        );
    }

    public function testTriggersSummaryGenerationForDocumentWithoutStatus(): void
    {
        // Arrange
        $document = $this->createDocument('doc-1');
        $this->documentGateway->save($document);

        $action = new TriggerDocumentSummaryAction(documentId: 'doc-1');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertTrue($this->messageBus->hasDispatched(DocumentSummaryTriggerAgent::class));
        $this->assertEquals(1, $this->messageBus->countDispatched(DocumentSummaryTriggerAgent::class));

        $updatedDocument = $this->documentGateway->get('doc-1');
        $this->assertEquals(SummaryStatus::PENDING, $updatedDocument->getSummaryStatus());
    }

    public function testSkipsWhenSummaryAlreadyCompleted(): void
    {
        // Arrange
        $document = $this->createDocument('doc-2');
        $document->setSummaryStatus(SummaryStatus::COMPLETED);
        $this->documentGateway->save($document);

        $action = new TriggerDocumentSummaryAction(documentId: 'doc-2');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertFalse($this->messageBus->hasDispatched(DocumentSummaryTriggerAgent::class));
        $this->assertEquals(0, $this->messageBus->countDispatched(DocumentSummaryTriggerAgent::class));

        $updatedDocument = $this->documentGateway->get('doc-2');
        $this->assertEquals(SummaryStatus::COMPLETED, $updatedDocument->getSummaryStatus());
    }

    public function testSkipsWhenSummaryAlreadyPending(): void
    {
        // Arrange
        $document = $this->createDocument('doc-3');
        $document->setSummaryStatus(SummaryStatus::PENDING);
        $this->documentGateway->save($document);

        $action = new TriggerDocumentSummaryAction(documentId: 'doc-3');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertFalse($this->messageBus->hasDispatched(DocumentSummaryTriggerAgent::class));
        $this->assertEquals(0, $this->messageBus->countDispatched(DocumentSummaryTriggerAgent::class));

        $updatedDocument = $this->documentGateway->get('doc-3');
        $this->assertEquals(SummaryStatus::PENDING, $updatedDocument->getSummaryStatus());
    }

    public function testTriggersSummaryGenerationForFailedStatus(): void
    {
        // Arrange
        $document = $this->createDocument('doc-4');
        $document->setSummaryStatus(SummaryStatus::FAILED);
        $this->documentGateway->save($document);

        $action = new TriggerDocumentSummaryAction(documentId: 'doc-4');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertTrue($this->messageBus->hasDispatched(DocumentSummaryTriggerAgent::class));
        $this->assertEquals(1, $this->messageBus->countDispatched(DocumentSummaryTriggerAgent::class));

        $updatedDocument = $this->documentGateway->get('doc-4');
        $this->assertEquals(SummaryStatus::PENDING, $updatedDocument->getSummaryStatus());
    }

    public function testThrowsExceptionWhenDocumentNotFound(): void
    {
        // Arrange
        $action = new TriggerDocumentSummaryAction(documentId: 'non-existent-doc');

        // Assert
        $this->expectException(DocumentNotFoundException::class);

        // Act
        ($this->handler)($action);
    }

    public function testDispatchesAgentWithCorrectData(): void
    {
        // Arrange
        $document = $this->createDocument('doc-5', 'Test content for summary');
        $this->documentGateway->save($document);

        $action = new TriggerDocumentSummaryAction(documentId: 'doc-5');

        // Act
        ($this->handler)($action);

        // Assert
        $this->assertTrue($this->messageBus->hasDispatched(DocumentSummaryTriggerAgent::class));

        $dispatchedMessages = $this->messageBus->getDispatchedMessages();
        $this->assertCount(1, $dispatchedMessages);

        $agent = $dispatchedMessages[0];
        $this->assertInstanceOf(DocumentSummaryTriggerAgent::class, $agent);
        $this->assertEquals('doc-5', $agent->data['id']);
        $this->assertEquals('Test content for summary', $agent->data['content']);
    }

    private function createDocument(string $id, string $content = 'Test content'): Document
    {
        return new Document(
            id: $id,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $content,
            status: DocumentStatus::VALIDATED,
        );
    }
}
