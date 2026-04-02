<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Document;

use App\Application\Agent\ValidateDocumentAITriggerAgent;
use App\Application\Document\TriggerDocumentAiValidationAction;
use App\Application\Document\TriggerDocumentAiValidationHandler;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\Exception\MissingReferenceSubjectException;
use App\Domain\Document\ManualValidationStatus;
use App\Domain\Document\ValidationReason;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

class TriggerDocumentAiValidationHandlerTest extends TestCase
{
    use EntityUtilsTrait;
    private TriggerDocumentAiValidationHandler $handler;
    private NullDocumentGateway $documentGateway;
    private MessageBusInterface&Stub $messageBus;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $this->messageBus = $this->createStub(MessageBusInterface::class);
        $this->buildHandler();
    }

    private function buildHandler(): void
    {
        $this->handler = new TriggerDocumentAiValidationHandler(
            $this->documentGateway,
            $this->messageBus,
            new NullLogger()
        );
    }

    public function testTriggersAiValidationForDocumentWithoutValidation(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $documentContent = 'Test document content for AI validation';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);
        $this->forcePropertyValue($watchFile, 'wf-456');

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');
        $this->forcePropertyValue($document, null, 'manualStatus');

        $this->documentGateway->save($document);

        // Assert document is saved with PENDING validation status
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($documentId, $documentContent, $referenceSubject) {
                if ($message instanceof ValidateDocumentAITriggerAgent) {
                    $data = $message->data;
                    $this->assertEquals($documentId, $data['id']);
                    $this->assertEquals($documentContent, $data['content']);
                    $this->assertEquals($referenceSubject->en, $data['referenceSubject']);

                    return true;
                }

                return false;
            }))
            ->willReturn(new Envelope(new ValidateDocumentAITriggerAgent([
                'id' => $documentId,
                'content' => $documentContent,
                'referenceSubject' => $referenceSubject->en,
            ])));

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - document should be saved with PENDING status
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::PENDING, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals($referenceSubject->en, $updatedDocument->getAiValidation()->referenceSubject);
    }

    public function testSkipsAiValidationWhenAlreadyValidated(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);

        $existingValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: new ValidationReason('Good match', 'Bonne correspondance'),
            processedAt: new \DateTimeImmutable(),
            referenceSubject: $referenceSubject->en
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, $existingValidation, 'aiValidation');

        $this->documentGateway->save($document);

        // Message bus should never be called
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - validation status should remain unchanged
        $updatedDocument = $this->documentGateway->get($documentId);
        $aiValidation = $updatedDocument->getAiValidation();
        $this->assertNotNull($aiValidation);
        $this->assertEquals(AiValidationStatus::VALIDATED, $aiValidation->status);
        $this->assertEquals(95, $aiValidation->confidenceScore);
    }

    public function testSkipsAiValidationWhenAlreadyPending(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);

        $existingValidation = new AIValidation(
            status: AiValidationStatus::PENDING,
            confidenceScore: 0,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: $referenceSubject->en
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, $existingValidation, 'aiValidation');

        $this->documentGateway->save($document);

        // Message bus should never be called
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - validation status should remain PENDING
        $updatedDocument = $this->documentGateway->get($documentId);
        $aiValidation = $updatedDocument->getAiValidation();
        $this->assertNotNull($aiValidation);
        $this->assertEquals(AiValidationStatus::PENDING, $aiValidation->status);
    }

    public function testRetriesAiValidationWhenPreviouslyFailed(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $documentContent = 'Test document content for retry';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);

        $failedValidation = new AIValidation(
            status: AiValidationStatus::FAILED,
            confidenceScore: 0,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null
        );

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, $failedValidation, 'aiValidation');

        $this->documentGateway->save($document);

        // Should dispatch trigger agent
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ValidateDocumentAITriggerAgent::class))
            ->willReturn(new Envelope(new ValidateDocumentAITriggerAgent([
                'id' => $documentId,
                'content' => $documentContent,
                'referenceSubject' => $referenceSubject->en,
            ])));

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - document should be updated with PENDING status for retry
        $updatedDocument = $this->documentGateway->get($documentId);
        $aiValidation = $updatedDocument->getAiValidation();
        $this->assertNotNull($aiValidation);
        $this->assertEquals(AiValidationStatus::PENDING, $aiValidation->status);
    }

    public function testSkipsAiValidationWhenManuallyAccepted(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);

        $user = new User(null, 'test@example.com', [], 'testuser');

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');
        $document->manuallyValidate(ManualValidationStatus::ACCEPTED, $user);

        $this->documentGateway->save($document);

        // Message bus should never be called
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - no AI validation should be set
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertEquals(ManualValidationStatus::ACCEPTED, $updatedDocument->getManualStatus());
        $this->assertNull($updatedDocument->getAiValidation());
    }

    public function testSkipsAiValidationWhenManuallyRefused(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);

        $user = new User(null, 'test@example.com', [], 'testuser');

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');
        $document->manuallyValidate(ManualValidationStatus::REFUSED, $user);

        $this->documentGateway->save($document);

        // Message bus should never be called
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - no AI validation should be set
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertEquals(ManualValidationStatus::REFUSED, $updatedDocument->getManualStatus());
        $this->assertNull($updatedDocument->getAiValidation());
    }

    public function testThrowsExceptionWhenDocumentNotFound(): void
    {
        // Arrange
        $documentId = 'non-existent-doc';

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Assert
        $this->expectException(DocumentNotFoundException::class);

        // Act
        ($this->handler)($action);
    }

    public function testThrowsExceptionWhenReferenceSubjectIsNull(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $watchFileId = 'wf-456';

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        // No reference subject set

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: 'Test content',
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');

        $this->documentGateway->save($document);

        // Message bus should never be called
        $messageBusMock->expects($this->never())
            ->method('dispatch');

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Assert
        $this->expectException(MissingReferenceSubjectException::class);
        $this->expectExceptionMessage('Document with ID "doc-123" has no reference subject from watch file');

        // Act
        ($this->handler)($action);
    }

    public function testDispatchesWithDispatchAfterCurrentBusStamp(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $documentContent = 'Test document content for AI validation';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');

        $this->documentGateway->save($document);

        // Verify DispatchAfterCurrentBusStamp is used
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(ValidateDocumentAITriggerAgent::class),
                $this->callback(function ($stamps) {
                    $this->assertIsArray($stamps);
                    $this->assertCount(1, $stamps);
                    $this->assertInstanceOf(DispatchAfterCurrentBusStamp::class, $stamps[0]);

                    return true;
                })
            )
            ->willReturn(new Envelope(new ValidateDocumentAITriggerAgent([
                'id' => $documentId,
                'content' => $documentContent,
                'referenceSubject' => $referenceSubject->en,
            ])));

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);
    }

    public function testUsesLlmVersionWhenAvailable(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $documentContent = 'Test document content for AI validation';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');
        $referenceSubjectLlm = 'LLM optimized version for document filtering about climate change impacts';

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);
        $watchFile->setReferenceSubjectLlm($referenceSubjectLlm);
        $this->forcePropertyValue($watchFile, 'wf-456');

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');
        $this->forcePropertyValue($document, null, 'manualStatus');

        $this->documentGateway->save($document);

        // Assert - should use LLM version, not human-readable version
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($documentId, $documentContent, $referenceSubjectLlm) {
                if ($message instanceof ValidateDocumentAITriggerAgent) {
                    $data = $message->data;
                    $this->assertEquals($documentId, $data['id']);
                    $this->assertEquals($documentContent, $data['content']);
                    // Should use LLM version instead of human-readable version
                    $this->assertEquals($referenceSubjectLlm, $data['referenceSubject']);

                    return true;
                }

                return false;
            }))
            ->willReturn(new Envelope(new ValidateDocumentAITriggerAgent([
                'id' => $documentId,
                'content' => $documentContent,
                'referenceSubject' => $referenceSubjectLlm,
            ])));

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - AI validation should store LLM version
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::PENDING, $updatedDocument->getAiValidation()->status);
        $this->assertEquals($referenceSubjectLlm, $updatedDocument->getAiValidation()->referenceSubject);
    }

    public function testFallsBackToEnglishVersionWhenLlmVersionIsNull(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $this->messageBus = $messageBusMock;
        $this->buildHandler();
        // Arrange
        $documentId = 'doc-123';
        $documentContent = 'Test document content for AI validation';
        $referenceSubject = new TranslatedText('Climate Change', 'Changement climatique');

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $watchFile->setReferenceSubject($referenceSubject);
        // No LLM version set - should fallback to English version
        $this->forcePropertyValue($watchFile, 'wf-456');

        $document = new Document(
            id: null,
            title: 'Test Document',
            excerpt: 'Test excerpt for validation',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: $documentContent,
        );
        $this->forcePropertyValue($document, $documentId);
        $this->forcePropertyValue($document, $watchFile, 'watchFile');
        $this->forcePropertyValue($document, null, 'aiValidation');
        $this->forcePropertyValue($document, null, 'manualStatus');

        $this->documentGateway->save($document);

        // Assert - should use English version as fallback
        $messageBusMock->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($documentId, $documentContent, $referenceSubject) {
                if ($message instanceof ValidateDocumentAITriggerAgent) {
                    $data = $message->data;
                    $this->assertEquals($documentId, $data['id']);
                    $this->assertEquals($documentContent, $data['content']);
                    // Should use English human-readable version as fallback
                    $this->assertEquals($referenceSubject->en, $data['referenceSubject']);

                    return true;
                }

                return false;
            }))
            ->willReturn(new Envelope(new ValidateDocumentAITriggerAgent([
                'id' => $documentId,
                'content' => $documentContent,
                'referenceSubject' => $referenceSubject->en,
            ])));

        $action = new TriggerDocumentAiValidationAction($documentId);

        // Act
        ($this->handler)($action);

        // Assert - AI validation should store English version
        $updatedDocument = $this->documentGateway->get($documentId);
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals($referenceSubject->en, $updatedDocument->getAiValidation()->referenceSubject);
    }
}
