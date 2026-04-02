<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\UpdateDocumentSummaryAction;
use App\Application\Document\UpdateDocumentSummaryHandler;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\InvalidSummaryStateException;
use App\Domain\Document\Exception\UpdateDocumentSummaryException;
use App\Domain\Document\Summary;
use App\Domain\Document\SummaryStatus;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(UpdateDocumentSummaryHandler::class)]
class UpdateDocumentSummaryHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private UpdateDocumentSummaryHandler $handler;
    private NullDocumentGateway $documentGateway;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $this->handler = new UpdateDocumentSummaryHandler($this->documentGateway, new NullLogger());
    }

    public function testUpdateDocumentWithValidSummary(): void
    {
        // Arrange
        $documentId = 'test-doc-123';
        $summary = new Summary('Résumé français', 'English summary');
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary);

        $document = $this->createTestDocument($documentId);
        $this->documentGateway->save($document);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertEquals($summary->getFr(), $updatedDocument->getSummary()?->getFr());
        $this->assertEquals($summary->getEn(), $updatedDocument->getSummary()?->getEn());
        $this->assertEquals(SummaryStatus::COMPLETED, $updatedDocument->getSummaryStatus());
        $this->assertNotNull($updatedDocument->getSummaryGeneratedAt());
    }

    public function testUpdateDocumentWithSummaryError(): void
    {
        // Arrange
        $documentId = 'test-doc-456';
        $errorMessage = 'Failed to generate summary';
        $action = new UpdateDocumentSummaryAction(
            documentId: $documentId,
            summary: null,
            summaryError: $errorMessage
        );

        $document = $this->createTestDocument($documentId);
        $this->documentGateway->save($document);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertNull($updatedDocument->getSummary());
        $this->assertEquals(SummaryStatus::FAILED, $updatedDocument->getSummaryStatus());
        $this->assertNotNull($updatedDocument->getSummaryGeneratedAt());
    }

    public function testUpdateDocumentWithPendingStatus(): void
    {
        // Arrange
        $documentId = 'test-doc-789';
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: null);

        $document = $this->createTestDocument($documentId);
        $this->documentGateway->save($document);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertNull($updatedDocument->getSummary());
        $this->assertEquals(SummaryStatus::PENDING, $updatedDocument->getSummaryStatus());
        $this->assertNotNull($updatedDocument->getSummaryGeneratedAt());
    }

    public function testSkipUpdateWhenDocumentNotFound(): void
    {
        // Arrange
        $documentId = 'non-existent-doc';
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: new Summary('Test', 'Test'));

        // Act & Assert
        ($this->handler)($action);
        $this->expectException(DocumentNotFoundException::class);
        $this->documentGateway->get($documentId);
    }

    public function testThrowExceptionWhenDocumentRetrievalFails(): void
    {
        // Arrange
        $documentId = 'error-doc';
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: new Summary('Test', 'Test'));

        $errorGateway = $this->createStub(DocumentGatewayInterface::class);
        $errorGateway->method('get')
            ->willThrowException(new \RuntimeException('Database connection failed'));

        $handlerWithErrorGateway = new UpdateDocumentSummaryHandler($errorGateway, new NullLogger());

        // Act & Assert
        $this->expectException(UpdateDocumentSummaryException::class);
        $this->expectExceptionMessage('Failed to retrieve document error-doc: Database connection failed');

        ($handlerWithErrorGateway)($action);
    }

    public function testThrowExceptionWhenDocumentSaveFails(): void
    {
        // Arrange
        $documentId = 'save-error-doc';
        $action = new UpdateDocumentSummaryAction(documentId: $documentId, summary: new Summary('Test', 'Test'));

        $document = $this->createTestDocument($documentId);

        $errorGateway = $this->createStub(DocumentGatewayInterface::class);
        $errorGateway->method('get')
            ->willReturn($document);
        $errorGateway->method('save')
            ->willThrowException(new \RuntimeException('OpenSearch save failed'));

        $handlerWithErrorGateway = new UpdateDocumentSummaryHandler($errorGateway, new NullLogger());

        // Act & Assert
        $this->expectException(UpdateDocumentSummaryException::class);
        $this->expectExceptionMessage('Failed to save document save-error-doc: OpenSearch save failed');

        ($handlerWithErrorGateway)($action);
    }

    public function testThrowExceptionWhenSummaryAndErrorProvided(): void
    {
        // Arrange
        $documentId = 'invalid-state-doc';
        $summary = new Summary('Résumé', 'Summary');
        // Act & Assert
        $this->expectException(InvalidSummaryStateException::class);
        $this->expectExceptionMessage(
            'Invalid summary state for document invalid-state-doc: both summary and error provided'
        );

        new UpdateDocumentSummaryAction(documentId: $documentId, summary: $summary, summaryError: 'Error message');
    }

    private function createTestDocument(string $id): Document
    {
        $document = new Document(
            id: $id,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable('2024-01-01'),
            dateCollect: new \DateTimeImmutable('2024-01-02'),
            content: 'Test content',
            status: DocumentStatus::VALIDATED,
            validationReason: null
        );

        $this->forcePropertyValue($document, $id);

        return $document;
    }
}
