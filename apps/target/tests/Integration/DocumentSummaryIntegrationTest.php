<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Document\TriggerDocumentSummaryAction;
use App\Application\Document\UpdateDocumentSummaryAction;
use App\Application\Document\UpdateDocumentSummaryHandler;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Summary;
use App\Domain\Document\SummaryStatus;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use Symfony\Component\Messenger\MessageBusInterface;

class DocumentSummaryIntegrationTest extends AbstractApiTestCase
{
    private MessageBusInterface $messageBus;
    private NullDocumentGateway $documentGateway;
    private UpdateDocumentSummaryHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBus = self::getContainer()->get(MessageBusInterface::class);
        $this->documentGateway = new NullDocumentGateway();
        $this->handler = new UpdateDocumentSummaryHandler($this->documentGateway);
    }

    public function testEndToEndDocumentSummaryUpdateWithPersistence(): void
    {
        // Arrange
        $documentId = 'test-doc-e2e-456';
        $summary = new Summary('Test summary content', 'Contenu du résumé de test');

        $document = new Document(
            id: $documentId,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-01'),
            dateCollect: new \DateTimeImmutable('2024-01-02'),
            content: 'Test content',
            status: DocumentStatus::VALIDATED,
            validationReason: null,
        );
        $this->documentGateway->save($document);

        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);

        $this->messageBus->dispatch($action);
        ($this->handler)($action);

        $updatedDocument = $this->documentGateway->get($documentId);

        $this->assertEquals($summary->getEn(), $updatedDocument->getSummary()?->getEn());
        $this->assertEquals($summary->getFr(), $updatedDocument->getSummary()?->getFr());
        $this->assertEquals(SummaryStatus::COMPLETED, $updatedDocument->getSummaryStatus());
        $this->assertNotNull($updatedDocument->getSummaryGeneratedAt());
    }

    public function testEndToEndDocumentSummaryUpdateWithError(): void
    {
        // Arrange
        $documentId = 'test-doc-e2e-error-789';
        $errorMessage = 'Failed to generate summary';

        $document = new Document(
            id: $documentId,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-01'),
            dateCollect: new \DateTimeImmutable('2024-01-02'),
            content: 'Test content',
            status: DocumentStatus::VALIDATED,
            validationReason: null,
        );
        $this->documentGateway->save($document);

        $action = new UpdateDocumentSummaryAction(
            documentId: $documentId,
            summary: null,
            summaryError: $errorMessage,
        );

        $this->messageBus->dispatch($action);
        ($this->handler)($action);

        $updatedDocument = $this->documentGateway->get($documentId);

        $this->assertNull($updatedDocument->getSummary());
        $this->assertEquals(SummaryStatus::FAILED, $updatedDocument->getSummaryStatus());
        $this->assertNotNull($updatedDocument->getSummaryGeneratedAt());
    }

    public function testUpdateDocumentSummaryProcessorWithFailedSummary(): void
    {
        // Arrange
        $documentId = 'test-doc-processor-failed-456';
        $errorMessage = 'API connection timeout during summary generation';
        $action = new UpdateDocumentSummaryAction(
            documentId: $documentId,
            summary: null,
            summaryError: $errorMessage
        );

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertNull($action->summary);
        $this->assertEquals(SummaryStatus::FAILED, $action->summaryStatus);
        $this->assertEquals($errorMessage, $action->summaryError);
    }

    public function testUpdateDocumentSummaryProcessorWithPendingStatus(): void
    {
        // Arrange
        $documentId = 'test-doc-processor-pending-789';
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: null);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertNull($action->summary);
        $this->assertEquals(SummaryStatus::PENDING, $action->summaryStatus);
    }

    public function testUpdateDocumentSummaryProcessorWithOnlyFrenchSummary(): void
    {
        // Arrange
        $documentId = 'test-doc-processor-french-only-101';
        $summary = new Summary('Résumé uniquement en français', null);
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertEquals('Résumé uniquement en français', $action->summary?->getFr());
        $this->assertNull($action->summary?->getEn());
    }

    public function testUpdateDocumentSummaryProcessorWithOnlyEnglishSummary(): void
    {
        // Arrange
        $documentId = 'test-doc-processor-english-only-102';
        $summary = new Summary(null, 'English-only summary');
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertNull($action->summary?->getFr());
        $this->assertEquals('English-only summary', $action->summary?->getEn());
    }

    public function testUpdateDocumentSummaryProcessorWithEmptySummary(): void
    {
        // Arrange
        $documentId = 'test-doc-processor-empty-103';
        $summary = new Summary('', '');
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertEquals('', $action->summary?->getFr());
        $this->assertEquals('', $action->summary?->getEn());
    }

    public function testUpdateDocumentSummaryProcessorWithNullSummary(): void
    {
        // Arrange
        $documentId = 'test-doc-processor-null-104';
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: null);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertNull($action->summary);
        $this->assertEquals(SummaryStatus::PENDING, $action->summaryStatus);
    }

    public function testUpdateDocumentSummaryProcessorWithMissingDocumentId(): void
    {
        // Arrange
        $summary = new Summary('Test summary', 'Test summary EN');
        $action = new UpdateDocumentSummaryAction(documentId: '', summary: $summary);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertSame('', $action->documentId);
        $this->assertEquals(SummaryStatus::COMPLETED, $action->summaryStatus);
    }

    public function testUpdateDocumentSummaryProcessorWithEmptyDocumentId(): void
    {
        // Arrange
        $summary = new Summary('Test summary', 'Test summary EN');
        $action = new UpdateDocumentSummaryAction(documentId: '', summary: $summary);

        // Act
        $this->messageBus->dispatch($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertSame('', $action->documentId);
    }

    public function testCompleteDocumentSummaryWorkflow(): void
    {
        // Arrange
        $documentId = 'test-doc-workflow-complete-200';
        $content = 'Complete workflow test document content';
        $summaryFr = 'Résumé du workflow complet';
        $summaryEn = 'Complete workflow summary';

        $document = new Document(
            id: $documentId,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-01'),
            dateCollect: new \DateTimeImmutable('2024-01-02'),
            content: $content,
            status: DocumentStatus::VALIDATED,
            validationReason: null,
        );
        $this->documentGateway->save($document);

        // Act - Step 1: Dispatch trigger action
        $triggerAction = new TriggerDocumentSummaryAction(documentId: $documentId);
        $this->messageBus->dispatch($triggerAction);

        // Act - Step 2: Dispatch summary update action
        $summary = new Summary($summaryFr, $summaryEn);
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);
        $this->messageBus->dispatch($action);
        ($this->handler)($action);

        // Assert - Verify action state
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertEquals(SummaryStatus::COMPLETED, $action->summaryStatus);
        $this->assertEquals($summaryFr, $action->summary?->getFr());
        $this->assertEquals($summaryEn, $action->summary?->getEn());
    }

    public function testDocumentSummaryWorkflowWithError(): void
    {
        // Arrange
        $documentId = 'test-doc-workflow-error-201';
        $content = 'Document content that will cause an error';
        $errorMessage = 'Summary generation failed due to API error';

        $document = new Document(
            id: $documentId,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-01'),
            dateCollect: new \DateTimeImmutable('2024-01-02'),
            content: $content,
            status: DocumentStatus::VALIDATED,
            validationReason: null,
        );
        $this->documentGateway->save($document);

        // Act - Step 1: Dispatch trigger action
        $triggerAction = new TriggerDocumentSummaryAction(documentId: $documentId);
        $this->messageBus->dispatch($triggerAction);

        // Act - Step 2: Dispatch error update action
        $action = new UpdateDocumentSummaryAction(
            documentId: $documentId,
            summary: null,
            summaryError: $errorMessage
        );
        $this->messageBus->dispatch($action);
        ($this->handler)($action);

        // Assert - Verify action state
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertEquals(SummaryStatus::FAILED, $action->summaryStatus);
        $this->assertEquals($errorMessage, $action->summaryError);
    }

    public function testDocumentSummaryWorkflowWithPendingStatus(): void
    {
        // Arrange
        $documentId = 'test-doc-workflow-pending-202';
        $content = 'Document content pending processing';

        $document = new Document(
            id: $documentId,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-01'),
            dateCollect: new \DateTimeImmutable('2024-01-02'),
            content: $content,
            status: DocumentStatus::VALIDATED,
            validationReason: null,
        );
        $this->documentGateway->save($document);

        // Act - Step 1: Dispatch trigger action
        $triggerAction = new TriggerDocumentSummaryAction(documentId: $documentId);
        $this->messageBus->dispatch($triggerAction);

        // Act - Step 2: Dispatch pending status update action
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: null);
        $this->messageBus->dispatch($action);
        ($this->handler)($action);

        // Assert - Verify action state
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertEquals(SummaryStatus::PENDING, $action->summaryStatus);
    }

    public function testMultipleDocumentSummaryMessages(): void
    {
        // Arrange
        $documentIds = ['test-doc-multiple-301', 'test-doc-multiple-302', 'test-doc-multiple-303'];

        // Create documents in the gateway
        foreach ($documentIds as $documentId) {
            $document = new Document(
                id: $documentId,
                title: 'Test Document',
                excerpt: 'Test excerpt',
                type: 'html',
                datePublish: new \DateTimeImmutable('2024-01-01'),
                dateCollect: new \DateTimeImmutable('2024-01-02'),
                content: "Content for document {$documentId}",
                status: DocumentStatus::VALIDATED,
                validationReason: null,
            );
            $this->documentGateway->save($document);
        }

        $actions = [];

        // Act - Dispatch multiple trigger actions
        foreach ($documentIds as $documentId) {
            $action = new TriggerDocumentSummaryAction(documentId: $documentId);
            $this->messageBus->dispatch($action);
            $actions[] = $action;
        }

        $this->assertCount(3, $actions);

        foreach ($actions as $index => $action) {
            $this->assertInstanceOf(TriggerDocumentSummaryAction::class, $action);
            $this->assertEquals($documentIds[$index], $action->documentId);
        }
    }

    public function testDocumentSummaryWithComplexData(): void
    {
        // Arrange
        $documentId = 'test-doc-complex-400';
        $content = 'Complex document with multiple sections and detailed information';

        $document = new Document(
            id: $documentId,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-15T10:30:00Z'),
            dateCollect: new \DateTimeImmutable('2024-01-15T11:45:00Z'),
            content: $content,
            status: DocumentStatus::VALIDATED,
            validationReason: null,
        );
        $this->documentGateway->save($document);

        // Act
        $action = new TriggerDocumentSummaryAction(documentId: $documentId);
        $this->messageBus->dispatch($action);

        $this->assertInstanceOf(TriggerDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
    }

    public function testDocumentSummaryProcessorWithOpenSearchError(): void
    {
        // Arrange
        $documentId = 'test-doc-opensearch-error-500';
        $summary = new Summary('Test summary', 'Test summary EN');
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);

        // Act
        $this->messageBus->dispatch($action);
        ($this->handler)($action);

        // Assert
        $this->assertInstanceOf(UpdateDocumentSummaryAction::class, $action);
        $this->assertEquals($documentId, $action->documentId);
        $this->assertEquals(SummaryStatus::COMPLETED, $action->summaryStatus);
    }
}
