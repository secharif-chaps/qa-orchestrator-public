<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Document\AddDocumentAction;
use App\Application\Document\AddDocumentHandler;
use App\Application\Document\TriggerDocumentAiValidationAction;
use App\Domain\Actor\Actor;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Exception\ValidationException;
use App\Domain\Document\ValidationReason;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AllowMockObjectsWithoutExpectations]
class AddDocumentHandlerTest extends TestCase
{
    private AddDocumentHandler $handler;
    private CollectTaskGatewayInterface&Stub $collectTaskGateway;
    private DocumentGatewayInterface&Stub $documentGateway;
    private ValidatorInterface&MockObject $validator;
    private MessageBusInterface&Stub $messageBus;

    protected function setUp(): void
    {
        $this->collectTaskGateway = $this->createStub(CollectTaskGatewayInterface::class);
        $this->documentGateway = $this->createStub(DocumentGatewayInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->messageBus->method('dispatch')
        ->willReturnCallback(fn ($message) => new Envelope($message));
        $this->handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $this->documentGateway,
            $this->validator,
            $this->messageBus
        );
    }

    public function testInvokeWithValidDocumentAndEmptyId(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $documentGatewayMock = $this->createMock(DocumentGatewayInterface::class);
        $this->documentGateway = $documentGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-123';
        $document = $this->createValidDocument(null);

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $documentGatewayMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Document $doc) use ($watchFile, $source, $actor) {
                return $doc->getWatchFile() === $watchFile
                    && $doc->getSource() === $source
                    && $doc->getActor() === $actor
                    && !empty($doc->getId());
            }));

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($document, $result);
        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertSame($source, $result->getSource());
        $this->assertSame($actor, $result->getActor());
    }

    public function testInvokeWithValidDocumentAndExistingId(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $documentGatewayMock = $this->createMock(DocumentGatewayInterface::class);
        $this->documentGateway = $documentGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-123';
        $documentId = 'existing-document-id';
        $document = $this->createValidDocument($documentId);

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $documentGatewayMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Document $doc) use ($watchFile, $source, $actor, $documentId) {
                return $doc->getWatchFile() === $watchFile
                    && $doc->getSource() === $source
                    && $doc->getActor() === $actor
                    && $doc->getId() === $documentId;
            }));

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($document, $result);
        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertSame($source, $result->getSource());
        $this->assertSame($actor, $result->getActor());
    }

    public function testInvokeWithValidationErrors(): void
    {
        // Arrange
        $collectTaskId = 'collect-task-123';
        $document = $this->createValidDocument('');

        $violation1 = $this->createMock(ConstraintViolation::class);
        $violation1->expects($this->any())
            ->method('getMessage')
            ->willReturn('Title cannot be blank');
        $violation1->expects($this->any())
            ->method('__toString')
            ->willReturn('Title cannot be blank');

        $violation2 = $this->createMock(ConstraintViolation::class);
        $violation2->expects($this->any())
            ->method('getMessage')
            ->willReturn('Content is too short');
        $violation2->expects($this->any())
            ->method('__toString')
            ->willReturn('Content is too short');

        $violations = new ConstraintViolationList([$violation1, $violation2]);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn($violations);

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Document validation failed: Title cannot be blank');

        ($this->handler)($action);
    }

    public function testInvokeWithCollectTaskNotFound(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'non-existent-task';
        $document = $this->createValidDocument('');

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willThrowException(new \DomainException('Collect task not found'));

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act & Assert
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Collect task not found');

        ($this->handler)($action);
    }

    public function testInvokeWithDocumentGatewaySaveFailure(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $documentGatewayMock = $this->createMock(DocumentGatewayInterface::class);
        $this->documentGateway = $documentGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-123';
        $document = $this->createValidDocument('');

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $documentGatewayMock->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('OpenSearch connection failed'));

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenSearch connection failed');

        ($this->handler)($action);
    }

    public function testInvokeWithComplexDocumentData(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $documentGatewayMock = $this->createMock(DocumentGatewayInterface::class);
        $this->documentGateway = $documentGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-456';
        $documentId = Uuid::v4()->toString();
        $document = new Document(
            $documentId,
            'Complex Document Title',
            'This is a complex document excerpt that contains multiple sentences and provides a comprehensive overview of the document content.',
            'pdf',
            new \DateTimeImmutable('2024-01-15 10:30:00'),
            new \DateTimeImmutable('2024-01-15 11:00:00'),
            'Complex document content with multiple paragraphs and detailed information.',
            DocumentStatus::PENDING,
            null,
            'en',
            true,
            'This document provides valuable insights',
            false
        );

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $documentGatewayMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Document $doc) use ($watchFile, $source, $actor, $documentId) {
                return $doc->getWatchFile() === $watchFile
                    && $doc->getSource() === $source
                    && $doc->getActor() === $actor
                    && 'Complex Document Title' === $doc->getTitle()
                    && 'pdf' === $doc->getType()
                    && true === $doc->isInteresting()
                    && 'This document provides valuable insights' === $doc->getInsight()
                    && $doc->getId() === $documentId;
            }));

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act
        $result = ($this->handler)($action);

        // Assert
        $this->assertSame($document, $result);
        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertSame($source, $result->getSource());
        $this->assertSame($actor, $result->getActor());
    }

    public function testInvokeTriggersAiValidationAction(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $documentGatewayMock = $this->createMock(DocumentGatewayInterface::class);
        $this->documentGateway = $documentGatewayMock;
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-789';
        $documentId = 'doc-with-ai-validation';
        $document = $this->createValidDocument($documentId);

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $documentGatewayMock->expects($this->once())
            ->method('save')
            ->with($document);

        // Assert that TriggerDocumentAiValidationAction is dispatched with correct documentId and stamp
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function ($message) use ($documentId) {
                    return $message instanceof TriggerDocumentAiValidationAction
                        && $message->documentId === $documentId;
                }),
                $this->callback(function ($stamps) {
                    return \is_array($stamps)
                        && 1 === \count($stamps)
                        && $stamps[0] instanceof DispatchAfterCurrentBusStamp;
                })
            )
            ->willReturn(new Envelope(new TriggerDocumentAiValidationAction($documentId)));

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act
        ($this->handler)($action);

        // Assert - expectations are verified automatically by PHPUnit
    }

    public function testInvokeCreatesNewDocumentWithProviderIdWhenNoExistingDocumentFound(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-001';
        $providerId = 'provider-id-123';
        $document = $this->createValidDocument(null);
        $document->setProviderId($providerId);

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($document)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act
        $result = ($handler)($action);

        // Assert
        $this->assertSame($providerId, $result->getProviderId());
        $this->assertSame($watchFile, $result->getWatchFile());
        $this->assertSame($source, $result->getSource());
        $this->assertSame($actor, $result->getActor());

        // Verify document was saved
        $savedDocument = $nullDocumentGateway->get($result->getId());
        $this->assertSame($result, $savedDocument);
        $this->assertEquals($providerId, $savedDocument->getProviderId());
    }

    public function testInvokeUpdatesExistingDocumentWhenFoundByProviderId(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-002';
        $providerId = 'raw-id-456';
        $existingDocumentId = 'existing-doc-id';

        // Create existing document (from raw_result)
        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setTitle('Raw Title');
        $existingDocument->setExcerpt('Raw excerpt');
        $existingDocument->setContent('Raw content');

        // Create new document (from document_refined_result) with refined data
        $newDocument = $this->createValidDocument(null);
        $newDocument->setProviderId($providerId);
        $newDocument->setTitle('Refined Title');
        $newDocument->setExcerpt('Refined excerpt');
        $newDocument->setContent('Refined content that is longer');

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->with($newDocument)
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->with($collectTaskId)
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert
        $this->assertSame($existingDocument, $result);
        $this->assertEquals($existingDocumentId, $result->getId());
        $this->assertEquals($providerId, $result->getProviderId());
        $this->assertEquals('Refined Title', $result->getTitle());
        $this->assertEquals('Refined excerpt', $result->getExcerpt());
        $this->assertEquals('Refined content that is longer', $result->getContent());
        $this->assertNotNull($result->getUpdatedAt());
    }

    public function testInvokeMergesDataWithRefinedDataTakingPriority(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-003';
        $providerId = 'raw-id-789';
        $existingDocumentId = 'existing-doc-789';

        // Existing document with raw data
        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setTitle('URL Title');
        $existingDocument->setExcerpt('Short excerpt');
        $existingDocument->setContent('Short content');
        $existingDocument->setUrl(null);
        $existingDocument->setCfcRestricted(false);

        // New document with refined data
        $newDocument = $this->createValidDocument(null);
        $newDocument->setProviderId($providerId);
        $newDocument->setTitle('Refined Title');
        $newDocument->setExcerpt('Longer refined excerpt with more details');
        $newDocument->setContent('Much longer refined content with comprehensive information');
        $newDocument->setUrl('https://example.com/refined');
        $newDocument->setCfcRestricted(true);

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert - Refined data should have priority
        $this->assertSame($existingDocument, $result);
        $this->assertEquals('Refined Title', $result->getTitle());
        $this->assertEquals('Longer refined excerpt with more details', $result->getExcerpt());
        $this->assertEquals('Much longer refined content with comprehensive information', $result->getContent());
        $this->assertEquals('https://example.com/refined', $result->getUrl());
        $this->assertTrue($result->isCfcRestricted());
    }

    public function testInvokeDoesNotOverwriteExistingFieldsWhenNewDataIsEmpty(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-004';
        $providerId = 'raw-id-101';
        $existingDocumentId = 'existing-doc-101';

        // Existing document with complete data
        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setTitle('Complete Title');
        $existingDocument->setExcerpt('Complete excerpt');
        $existingDocument->setContent('Complete content');
        $existingDocument->setUrl('https://example.com/existing');

        // New document with minimal data (empty title, excerpt, content)
        $newDocument = new Document(
            null,
            '',
            '',
            'html',
            new \DateTimeImmutable('2024-01-15 10:30:00'),
            new \DateTimeImmutable('2024-01-15 11:00:00'),
            '', // Empty content
            DocumentStatus::PENDING,
            null,
            'en',
            false,
            null,
            false
        );
        $newDocument->setProviderId($providerId);

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert - Existing data should be preserved when new data is empty
        $this->assertSame($existingDocument, $result);
        $this->assertEquals('Complete Title', $result->getTitle());
        $this->assertEquals('Complete excerpt', $result->getExcerpt());
        $this->assertEquals('Complete content', $result->getContent());
        $this->assertEquals('https://example.com/existing', $result->getUrl());
    }

    public function testInvokePrefersLongerContentWhenMerging(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-005';
        $providerId = 'raw-id-202';
        $existingDocumentId = 'existing-doc-202';

        // Existing document with shorter content
        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setContent('Short content');

        // New document with longer content
        $newDocument = $this->createValidDocument(null);
        $newDocument->setProviderId($providerId);
        $newDocument->setContent(
            'This is a much longer content that should be preferred because it contains more information and details.'
        );

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert - Longer content should be preferred
        $this->assertSame($existingDocument, $result);
        $this->assertEquals(
            'This is a much longer content that should be preferred because it contains more information and details.',
            $result->getContent()
        );
    }

    public function testInvokeDoesNotSearchByProviderIdWhenProviderIdIsNull(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-006';
        $document = $this->createValidDocument(null);
        // providerId is null by default

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $document);

        // Act
        $result = ($handler)($action);

        // Assert
        $this->assertNull($result->getProviderId());
        $this->assertSame($watchFile, $result->getWatchFile());

        // Verify document was saved as new
        $savedDocument = $nullDocumentGateway->get($result->getId());
        $this->assertSame($result, $savedDocument);
    }

    public function testInvokeDoesNotTriggerAiValidationWhenDocumentExistsAndHasAiValidation(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-007';
        $providerId = 'raw-id-303';
        $existingDocumentId = 'existing-doc-303';

        // Existing document with AI validation already done
        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setAiValidation(new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: new ValidationReason(en: 'Relevant document', fr: 'Document pertinent'),
            processedAt: new \DateTimeImmutable(),
            referenceSubject: 'Test Subject'
        ));

        // New document with same providerId
        $newDocument = $this->createValidDocument(null);
        $newDocument->setProviderId($providerId);
        $newDocument->setTitle('Updated Title');

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        // Assert that AI validation is NOT triggered when document exists and has AI validation
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert
        $this->assertSame($existingDocument, $result);
        $this->assertNotNull($result->getAiValidation());
    }

    public function testInvokeDoesNotOverwriteRefinedTitleWithUntitledDocument(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange: refined result arrived first with a real title
        $collectTaskId = 'collect-task-raw-009';
        $providerId = 'raw-id-untitled';
        $existingDocumentId = 'existing-doc-untitled';

        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setTitle('Real Refined Title');
        $existingDocument->setExcerpt('A proper refined excerpt with meaningful content');

        // Raw result arrives second with "Untitled Document" fallback
        $newDocument = $this->createValidDocument(null);
        $newDocument->setProviderId($providerId);
        $newDocument->setTitle('Untitled Document');
        $newDocument->setExcerpt('Short auto');

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert - "Untitled Document" must NOT overwrite the real refined title
        $this->assertSame($existingDocument, $result);
        $this->assertEquals('Real Refined Title', $result->getTitle());
        // Shorter excerpt should NOT overwrite longer existing excerpt
        $this->assertEquals('A proper refined excerpt with meaningful content', $result->getExcerpt());
    }

    public function testInvokeUpdatesTypeAndDatePublishWhenDifferent(): void
    {
        $collectTaskGatewayMock = $this->createMock(CollectTaskGatewayInterface::class);
        $this->collectTaskGateway = $collectTaskGatewayMock;
        $this->buildHandler();
        // Arrange
        $collectTaskId = 'collect-task-raw-008';
        $providerId = 'raw-id-404';
        $existingDocumentId = 'existing-doc-404';

        // Existing document
        $existingDocument = $this->createValidDocument($existingDocumentId);
        $existingDocument->setProviderId($providerId);
        $existingDocument->setType('html');
        $existingDocument->setDatePublish(new \DateTimeImmutable('2024-01-01 10:00:00'));

        // New document with different type and date
        $newDocument = $this->createValidDocument(null);
        $newDocument->setProviderId($providerId);
        $newDocument->setType('pdf');
        $newDocument->setDatePublish(new \DateTimeImmutable('2024-01-15 15:30:00'));

        $watchFile = $this->createWatchFile();
        $actor = $this->createActor();
        $source = $this->createSource($actor, $watchFile);
        $collectTask = $this->createCollectTask($source, $watchFile);

        $nullDocumentGateway = new NullDocumentGateway();
        $nullDocumentGateway->save($existingDocument);

        $this->validator->expects($this->once())
            ->method('validate')
            ->willReturn(new ConstraintViolationList());

        $collectTaskGatewayMock->expects($this->once())
            ->method('get')
            ->willReturn($collectTask);

        $handler = new AddDocumentHandler(
            $this->collectTaskGateway,
            $nullDocumentGateway,
            $this->validator,
            $this->messageBus
        );

        $action = new AddDocumentAction($collectTaskId, $newDocument);

        // Act
        $result = ($handler)($action);

        // Assert
        $this->assertSame($existingDocument, $result);
        $this->assertEquals('pdf', $result->getType());
        $this->assertEquals(new \DateTimeImmutable('2024-01-15 15:30:00'), $result->getDatePublish());
    }

    private function createValidDocument(?string $id): Document
    {
        return new Document(
            $id,
            'Test Document Title',
            'This is a test document excerpt that provides a brief overview of the content.',
            'html',
            new \DateTimeImmutable('2024-01-15 10:30:00'),
            new \DateTimeImmutable('2024-01-15 11:00:00'),
            'Test document content with some sample text for testing purposes.',
            DocumentStatus::PENDING,
            null,
            'en',
            false,
            null,
            false
        );
    }

    private function createWatchFile(): WatchFile
    {
        return new WatchFile('Test Watch File', 'Test user objective for monitoring', new Organisation(
            'Test Org',
            'test-org-id'
        ));
    }

    private function createActor(): Actor
    {
        return new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
    }

    private function createSource(Actor $actor, WatchFile $watchFile): Source
    {
        return new Source(
            'Test Source',
            TranslatedText::fromArray([
                'en' => 'Test source description',
                'fr' => 'Description de la source de test',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'en' => 'High relevance',
                'fr' => 'Pertinence élevée',
            ]),
            $actor,
            $watchFile
        );
    }

    private function createCollectTask(Source $source, WatchFile $watchFile): CollectTask
    {
        return new CollectTask(
            $source,
            $watchFile,
            'test-provider',
            [
                'test' => 'configuration',
            ],
            'provider-task-123',
            CollectTaskStatus::CREATED
        );
    }
}
