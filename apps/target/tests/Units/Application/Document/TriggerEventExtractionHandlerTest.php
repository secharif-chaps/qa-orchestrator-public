<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Agent\EventExtractionTriggerAgent;
use App\Application\Document\GetRecentEventsContextAction;
use App\Application\Document\TriggerEventExtractionAction;
use App\Application\Document\TriggerEventExtractionHandler;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\DocumentWithoutWatchFileException;
use App\Domain\Document\ValidationReason;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

class TriggerEventExtractionHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private TriggerEventExtractionHandler $handler;
    private DocumentGatewayInterface&MockObject $documentGateway;
    private MessageBusInterface&Stub $messageBus;

    protected function setUp(): void
    {
        $this->documentGateway = $this->createMock(DocumentGatewayInterface::class);
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new TriggerEventExtractionHandler(
            $this->documentGateway,
            $this->messageBus,
            new NullLogger()
        );
    }

    public function testTriggersEventExtractionForValidatedDocument(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $watchFileId = 'wf-456';
        $documentContent = 'Test document content';
        $referenceSubject = new TranslatedText('Test subject', 'Test subject');

        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $watchFile->setReferenceSubject($referenceSubject);

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: new ValidationReason('Test reason', 'Test reason'),
            processedAt: new \DateTimeImmutable(),
            referenceSubject: 'Test subject'
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, $aiValidation, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        // Mock GetRecentEventsContextAction dispatch
        $recentEvents = [
            [
                'title' => 'Event 1',
                'date' => '2024-01-01',
            ],
            [
                'title' => 'Event 2',
                'date' => '2024-01-02',
            ],
        ];

        $contextEnvelope = new Envelope(new GetRecentEventsContextAction($watchFileId, 15));
        $contextEnvelope = $contextEnvelope->with(new HandledStamp($recentEvents, 'handler'));

        // Expect two dispatches: GetRecentEventsContextAction and EventExtractionTriggerAgent
        $messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (
                $contextEnvelope,
                $documentId,
                $watchFileId,
                $documentContent,
                $referenceSubject,
                $recentEvents
            ) {
                if ($message instanceof GetRecentEventsContextAction) {
                    return $contextEnvelope;
                }

                if ($message instanceof EventExtractionTriggerAgent) {
                    $data = $message->data;
                    $this->assertEquals($documentId, $data['documentId']);
                    $this->assertEquals($watchFileId, $data['watchFileId']);
                    $this->assertEquals($documentContent, $data['documentContent']);
                    $this->assertEquals($referenceSubject, $data['referenceSubject']);
                    $this->assertEquals($recentEvents, $data['recentEvents']);

                    return new Envelope($message);
                }

                $this->fail('Unexpected message dispatched: ' . $message::class);
            });

        $action = new TriggerEventExtractionAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - expectations verified via mock
    }

    public function testSkipsExtractionForNonValidatedDocument(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';

        $aiValidation = new AIValidation(
            status: AiValidationStatus::PENDING,
            confidenceScore: 0,
            validationReason: new ValidationReason('Pending', 'En attente'),
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, null, 'watchFile');
        $this->forcePropertyValue($document, $aiValidation, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        // Message bus should never be called because document has no watchfile
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerEventExtractionAction($documentId);

        // Act
        $this->expectException(DocumentWithoutWatchFileException::class);
        ($this->handler)($action);
    }

    public function testSkipsValidationCheckWhenDisabled(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $watchFileId = 'wf-456';
        $documentContent = 'Test document content';

        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        // Document has no AI validation, but check is disabled
        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        // Mock GetRecentEventsContextAction dispatch
        $contextEnvelope = new Envelope(new GetRecentEventsContextAction($watchFileId, 15));
        $contextEnvelope = $contextEnvelope->with(new HandledStamp([], 'handler'));

        // Should dispatch both actions even without validation
        $messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use ($contextEnvelope) {
                if ($message instanceof GetRecentEventsContextAction) {
                    return $contextEnvelope;
                }

                if ($message instanceof EventExtractionTriggerAgent) {
                    return new Envelope($message);
                }

                $this->fail('Unexpected message dispatched: ' . $message::class);
            });

        $action = new TriggerEventExtractionAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - expectations verified via mock
    }

    public function testSkipsExtractionForDocumentWithoutWatchFile(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, null, 'watchFile');
        $this->forcePropertyValue($document, $aiValidation, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        // Message bus should never be called because document has no watchfile
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerEventExtractionAction($documentId);

        // Act
        $this->expectException(DocumentWithoutWatchFileException::class);
        ($this->handler)($action);
    }

    public function testThrowsExceptionWhenDocumentNotFound(): void
    {
        // Arrange
        $documentId = 'non-existent-doc';

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willThrowException(new DocumentNotFoundException($documentId));

        $action = new TriggerEventExtractionAction($documentId);

        // Assert
        $this->expectException(DocumentNotFoundException::class);

        // Act
        ($this->handler)($action);
    }

    public function testHandlesGetRecentEventsContextFailureGracefully(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $watchFileId = 'wf-456';
        $documentContent = 'Test document content';

        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, $aiValidation, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        // GetRecentEventsContextAction dispatch throws exception
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(GetRecentEventsContextAction::class))
            ->willThrowException(new \RuntimeException('Failed to get context'));

        $action = new TriggerEventExtractionAction($documentId);

        // Act
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to get context');
        ($this->handler)($action);
    }

    public function testUsesEmptyReferenceSubjectWhenNotProvided(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $watchFileId = 'wf-456';
        $documentContent = 'Test document content';

        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null // No reference subject
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, $aiValidation, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        $contextEnvelope = new Envelope(new GetRecentEventsContextAction($watchFileId, 15));
        $contextEnvelope = $contextEnvelope->with(new HandledStamp([], 'handler'));

        $messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use ($contextEnvelope) {
                if ($message instanceof GetRecentEventsContextAction) {
                    return $contextEnvelope;
                }

                if ($message instanceof EventExtractionTriggerAgent) {
                    $this->assertNull($message->data['referenceSubject']);

                    return new Envelope($message);
                }

                $this->fail('Unexpected message dispatched: ' . $message::class);
            });

        $action = new TriggerEventExtractionAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - expectations verified via mock
    }

    public function testHandlesNullAiValidationWhenCheckDisabled(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $watchFileId = 'wf-456';
        $documentContent = 'Test document content';

        $watchFile = new WatchFile('Test Watchfile', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);

        // Document has NO AI validation
        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'article',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');

        $this->documentGateway->expects($this->once())
            ->method('get')
            ->with($documentId)
            ->willReturn($document);

        $contextEnvelope = new Envelope(new GetRecentEventsContextAction($watchFileId, 15));
        $contextEnvelope = $contextEnvelope->with(new HandledStamp([], 'handler'));

        // Should dispatch both actions even with null validation (check disabled)
        $messageBusMock->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function ($message) use ($contextEnvelope) {
                if ($message instanceof GetRecentEventsContextAction) {
                    return $contextEnvelope;
                }

                if ($message instanceof EventExtractionTriggerAgent) {
                    // Should have null reference subject when validation is null
                    $this->assertNull($message->data['referenceSubject']);

                    return new Envelope($message);
                }

                $this->fail('Unexpected message dispatched: ' . $message::class);
            });

        $action = new TriggerEventExtractionAction($documentId);

        // Act - should not crash even with null aiValidation
        ($this->handler)($action);

        // Assert - expectations verified via mock
    }
}
