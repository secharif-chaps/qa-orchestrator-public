<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Document;

use App\Application\Document\EnrichDocumentWithAiValidationAction;
use App\Application\Document\EnrichDocumentWithAiValidationHandler;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\ValidationReason;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\InvalidArgumentException;

#[CoversClass(Document::class)]
#[CoversClass(AIValidation::class)]
#[CoversClass(ValidationReason::class)]
#[CoversClass(EnrichDocumentWithAiValidationHandler::class)]
class DocumentAiValidationTest extends TestCase
{
    private EnrichDocumentWithAiValidationHandler $handler;
    private NullDocumentGateway $documentGateway;

    protected function setUp(): void
    {
        $this->documentGateway = new NullDocumentGateway();
        $messageBus = $this->createStub(\Symfony\Component\Messenger\MessageBusInterface::class);
        // Mock the dispatch method to return an Envelope (needed for TriggerEventExtractionAction)
        $messageBus->method('dispatch')
            ->willReturnCallback(function ($message) {
                return new \Symfony\Component\Messenger\Envelope($message);
            });

        $this->handler = new EnrichDocumentWithAiValidationHandler(
            documentGateway: $this->documentGateway,
            messageBus: $messageBus,
        );
    }

    public function testValidationReasonEmptyDetection(): void
    {
        // Arrange & Act
        $reason = new ValidationReason();

        // Assert
        $this->assertTrue($reason->isEmpty());
    }

    public function testValidationReasonWithOnlyEnglishIsNotEmpty(): void
    {
        // Arrange & Act
        $reason = new ValidationReason(en: 'English reason');

        // Assert
        $this->assertFalse($reason->isEmpty());
    }

    public function testValidationReasonWithOnlyFrenchIsNotEmpty(): void
    {
        // Arrange & Act
        $reason = new ValidationReason(fr: 'Raison française');

        // Assert
        $this->assertFalse($reason->isEmpty());
    }

    public function testValidationReasonWithBothLanguagesIsNotEmpty(): void
    {
        // Arrange & Act
        $reason = new ValidationReason(fr: 'Raison française', en: 'English reason');

        // Assert
        $this->assertFalse($reason->isEmpty());
    }

    public function testValidationReasonWithNullValuesIsEmpty(): void
    {
        // Arrange & Act
        $reason = new ValidationReason(null, null);

        // Assert
        $this->assertTrue($reason->isEmpty());
    }

    public function testAiValidationConfidenceScoreValidationRejectsInvalidValues(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence score must be between 0 and 100');

        // Act
        new AIValidation(
            status: AiValidationStatus::PENDING,
            confidenceScore: -10,
            validationReason: null,
            processedAt: new \DateTimeImmutable()
        );
    }

    public function testAiValidationConfidenceScoreValidationRejectsScoreAboveHundred(): void
    {
        // Arrange
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence score must be between 0 and 100');

        // Act
        new AIValidation(
            status: AiValidationStatus::PENDING,
            confidenceScore: 101,
            validationReason: null,
            processedAt: new \DateTimeImmutable()
        );
    }

    public function testAiValidationSerialization(): void
    {
        // Arrange
        $validationReason = new ValidationReason(
            en: 'Content is highly relevant and accurate',
            fr: 'Analyse complète avec informations de qualité'
        );
        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 14:30:00'),
            referenceSubject: 'Technology and AI'
        );

        // Act & Assert - Test that AIValidation can be created and accessed
        $this->assertEquals(AiValidationStatus::VALIDATED, $aiValidation->status);
        $this->assertEquals(95, $aiValidation->confidenceScore);
        $this->assertEquals('Technology and AI', $aiValidation->referenceSubject);
        $this->assertInstanceOf(\DateTimeImmutable::class, $aiValidation->processedAt);
        $this->assertInstanceOf(ValidationReason::class, $aiValidation->validationReason);
        $this->assertEquals('Content is highly relevant and accurate', $aiValidation->validationReason->en);
        $this->assertEquals('Analyse complète avec informations de qualité', $aiValidation->validationReason->fr);
    }

    public function testIsAiValidatedReturnsFalseWhenNoAIValidation(): void
    {
        // Arrange & Act
        $document = new Document(
            id: 'test-doc-1',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        // Assert
        $this->assertFalse($document->isAiValidated());
    }

    public function testIsAiValidatedReturnsTrueWhenAIValidationExists(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-2',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 90,
            validationReason: null,
            processedAt: new \DateTimeImmutable()
        );
        $document->setAiValidation($aiValidation);

        // Act & Assert
        $this->assertTrue($document->isAiValidated());
    }

    public function testIsAiValidatedReturnsFalseWithRejectedStatus(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-3',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::REJECTED,
            confidenceScore: 15,
            validationReason: new ValidationReason('Rejected', 'Rejeté'),
            processedAt: new \DateTimeImmutable()
        );

        $document->setAiValidation($aiValidation);

        // Act & Assert - isAiValidated() only returns true for VALIDATED status
        $this->assertFalse($document->isAiValidated());
        $this->assertTrue($document->isAiRejected());
    }

    public function testSetAiValidationToNullMakesDocumentNotAiValidated(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-4',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 90,
            validationReason: null,
            processedAt: new \DateTimeImmutable()
        );
        $document->setAiValidation($aiValidation);
        $this->assertTrue($document->isAiValidated());

        // Act
        $document->setAiValidation(null);

        // Assert
        $this->assertFalse($document->isAiValidated());
    }

    public function testEnrichesDocumentWithAIValidationSuccessfully(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-5',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'Document content is valid and meets quality standards',
            fr: 'Le contenu du document est valide et respecte les standards de qualité'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 85,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 10:30:00'),
            referenceSubject: 'Technology and AI'
        );

        $action = new EnrichDocumentWithAiValidationAction(documentId: 'test-doc-5', aiValidation: $aiValidation);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-5');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertSame($aiValidation, $updatedDocument->getAiValidation());
        $this->assertTrue($updatedDocument->isAiValidated());
    }

    public function testUpdatesExistingAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-6',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $initialValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 45,
            validationReason: new ValidationReason('Initial', 'Initial'),
            processedAt: new \DateTimeImmutable('2024-01-15 09:00:00'),
            referenceSubject: 'Initial Subject'
        );
        $document->setAiValidation($initialValidation);

        $this->documentGateway->save($document);

        $updatedValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 90,
            validationReason: new ValidationReason('Updated', 'Mis à jour'),
            processedAt: new \DateTimeImmutable('2024-01-15 11:00:00'),
            referenceSubject: 'Updated Subject'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'test-doc-6',
            aiValidation: $updatedValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-6');
        $this->assertSame($updatedValidation, $updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(90, $updatedDocument->getAiValidation()->confidenceScore);
    }

    public function testHandlesNonExistentDocument(): void
    {
        // Arrange
        $validationReason = new ValidationReason('Valid', 'Valide');
        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 80,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable()
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'non-existent-doc',
            aiValidation: $aiValidation
        );

        // Act & Assert
        $this->expectException(DocumentNotFoundException::class);
        $this->expectExceptionMessage('Document with ID "non-existent-doc" not found');

        ($this->handler)($action);
    }

    public function testHandlesNullValidationReason(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-7',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 75,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null
        );

        $action = new EnrichDocumentWithAiValidationAction(documentId: 'test-doc-7', aiValidation: $aiValidation);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-7');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertTrue($updatedDocument->isAiValidated());
    }

    public function testHandlesReferenceSubjectCorrectly(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-8',
            title: 'Test Document with Reference Subject',
            content: 'Test content about AI and technology',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'Content is highly relevant and accurate',
            fr: 'Analyse complète avec informations de qualité'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 14:30:00'),
            referenceSubject: 'Technology and AI'
        );

        $action = new EnrichDocumentWithAiValidationAction(documentId: 'test-doc-8', aiValidation: $aiValidation);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-8');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(95, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals(
            'Content is highly relevant and accurate',
            $updatedDocument->getAiValidation()
->validationReason?->en
        );
        $this->assertEquals(
            'Analyse complète avec informations de qualité',
            $updatedDocument->getAiValidation()
->validationReason?->fr
        );
        $this->assertEquals('Technology and AI', $updatedDocument->getAiValidation()->referenceSubject);
    }

    public function testHandlesNullReferenceSubject(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-9',
            title: 'Test Document without Reference Subject',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 80,
            validationReason: null,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: null
        );

        $action = new EnrichDocumentWithAiValidationAction(documentId: 'test-doc-9', aiValidation: $aiValidation);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-9');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(80, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
    }

    public function testIsAiValidationUncertainReturnsTrueWithUncertainStatus(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-10',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 65,
            validationReason: new ValidationReason('Uncertain', 'Incertain'),
            processedAt: new \DateTimeImmutable()
        );

        $document->setAiValidation($aiValidation);

        // Act & Assert
        $this->assertTrue($document->isAiValidationUncertain());
        $this->assertFalse($document->isAiValidated());
        $this->assertFalse($document->isAiRejected());
        $this->assertFalse($document->isAiValidationFailed());
        $this->assertFalse($document->isAiValidationPending());
    }

    public function testIsAiValidationFailedReturnsTrueWithFailedStatus(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-11',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::FAILED,
            confidenceScore: 0,
            validationReason: new ValidationReason('Failed', 'Échec'),
            processedAt: new \DateTimeImmutable()
        );

        $document->setAiValidation($aiValidation);

        // Act & Assert
        $this->assertTrue($document->isAiValidationFailed());
        $this->assertFalse($document->isAiValidated());
        $this->assertFalse($document->isAiRejected());
        $this->assertFalse($document->isAiValidationUncertain());
        $this->assertFalse($document->isAiValidationPending());
    }

    public function testIsAiValidationPendingReturnsTrueWithPendingStatus(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-12',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::PENDING,
            confidenceScore: 0,
            validationReason: new ValidationReason('Pending', 'En attente'),
            processedAt: new \DateTimeImmutable()
        );

        $document->setAiValidation($aiValidation);

        // Act & Assert
        $this->assertTrue($document->isAiValidationPending());
        $this->assertFalse($document->isAiValidated());
        $this->assertFalse($document->isAiRejected());
        $this->assertFalse($document->isAiValidationFailed());
        $this->assertFalse($document->isAiValidationUncertain());
    }

    public function testEnrichDocumentWithFailedAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-14',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'AI validation failed due to technical error',
            fr: 'La validation IA a échoué en raison d\'une erreur technique'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::FAILED,
            confidenceScore: 0,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: 'Failed Subject'
        );

        $action = new EnrichDocumentWithAiValidationAction(documentId: 'test-doc-14', aiValidation: $aiValidation);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-14');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals('Failed Subject', $updatedDocument->getAiValidation()->referenceSubject);
        $this->assertTrue($updatedDocument->isAiValidationFailed());
        $this->assertFalse($updatedDocument->isAiValidated());
    }

    public function testEnrichDocumentWithUncertainAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-13',
            title: 'Test Document',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'Document relevance is uncertain and requires manual review',
            fr: 'La pertinence du document est incertaine et nécessite une révision manuelle'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 60,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 12:00:00'),
            referenceSubject: 'Uncertain Subject'
        );

        $action = new EnrichDocumentWithAiValidationAction(documentId: 'test-doc-13', aiValidation: $aiValidation);

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-13');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::UNCERTAIN, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(60, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertTrue($updatedDocument->isAiValidationUncertain());
        $this->assertFalse($updatedDocument->isAiValidated());
    }

    public function testEnrichDocumentWithValidationError(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-error',
            title: 'Test Document with Error',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationError = 'Authorization failed - please check your credentials';
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'test-doc-error',
            validationError: $validationError
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-error');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
        $this->assertTrue($updatedDocument->isAiValidationFailed());
        $this->assertFalse($updatedDocument->isAiValidated());
        $this->assertFalse($updatedDocument->isAiValidationUncertain());
    }

    public function testEnrichDocumentWithValidationErrorWithExistingAiValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-error-existing',
            title: 'Test Document with Existing Validation and Error',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $existingAiValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 50,
            validationReason: new ValidationReason('Previous validation', 'Validation précédente'),
            processedAt: new \DateTimeImmutable('-1 hour'),
            referenceSubject: 'Previous subject'
        );
        $document->setAiValidation($existingAiValidation);
        $this->documentGateway->save($document);

        $validationError = 'Network timeout during validation process';
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'test-doc-error-existing',
            validationError: $validationError
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-error-existing');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
        $this->assertTrue($updatedDocument->isAiValidationFailed());
        $this->assertFalse($updatedDocument->isAiValidated());
        $this->assertFalse($updatedDocument->isAiValidationUncertain());
    }

    public function testEnrichNonExistentDocumentWithValidationErrorThrowsException(): void
    {
        // Arrange
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'non-existent-document-error',
            validationError: 'Some validation error occurred'
        );

        // Act & Assert
        $this->expectException(DocumentNotFoundException::class);
        $this->expectExceptionMessage('Document with ID "non-existent-document-error" not found');
        ($this->handler)($action);
    }

    public function testValidationErrorWithSpecialCharacters(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-special-chars-error',
            title: 'Test Document with Special Characters Error',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationError = 'Error with special characters: éàçù & "quotes" and <tags> - Network timeout: 500ms';
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'test-doc-special-chars-error',
            validationError: $validationError
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-special-chars-error');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
        $this->assertTrue($updatedDocument->isAiValidationFailed());
    }

    public function testValidationErrorWithEmptyString(): void
    {
        // Arrange
        $document = new Document(
            id: 'test-doc-empty-error',
            title: 'Test Document with Empty Error',
            content: 'Test content',
            status: DocumentStatus::PENDING,
            validationReason: null,
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'test-doc-empty-error',
            validationError: ''
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('test-doc-empty-error');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
        $this->assertTrue($updatedDocument->isAiValidationFailed());
    }
}
