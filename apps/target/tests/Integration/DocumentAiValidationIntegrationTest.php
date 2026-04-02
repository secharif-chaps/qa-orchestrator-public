<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Document\EnrichDocumentWithAiValidationAction;
use App\Application\Document\EnrichDocumentWithAiValidationHandler;
use App\Domain\Document\AIValidation;
use App\Domain\Document\AiValidationStatus;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Domain\Document\ValidationReason;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\InvalidArgumentException;

class DocumentAiValidationIntegrationTest extends KernelTestCase
{
    private EnrichDocumentWithAiValidationHandler $handler;
    private NullDocumentGateway $documentGateway;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

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

    public function testEnrichDocumentWithAIValidationEndToEnd(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-doc-1',
            title: 'Integration Test Document',
            content: 'This is a test document for AI validation integration testing.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document ready for AI validation',
                fr: 'Document prêt pour la validation IA'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'AI validation confirms document meets quality standards',
            fr: 'La validation IA confirme que le document respecte les standards de qualité'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 92,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 14:30:00'),
            referenceSubject: 'Technology and AI'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-doc-1',
            aiValidation: $aiValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-doc-1');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(92, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals(
            'AI validation confirms document meets quality standards',
            $updatedDocument->getAiValidation()
->validationReason?->en
        );
        $this->assertEquals(
            'La validation IA confirme que le document respecte les standards de qualité',
            $updatedDocument->getAiValidation()
->validationReason?->fr
        );
        $this->assertTrue($updatedDocument->isAiValidated());
    }

    public function testEnrichDocumentWithRejectedAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-doc-2',
            title: 'Rejected Document',
            content: 'This document contains invalid content that should be rejected.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document pending AI validation',
                fr: 'Document en attente de validation IA'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'AI validation detected inappropriate content',
            fr: 'La validation IA a détecté du contenu inapproprié'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 15:00:00'),
            referenceSubject: 'Inappropriate Content'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-doc-2',
            aiValidation: $aiValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-doc-2');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(95, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals(
            'AI validation detected inappropriate content',
            $updatedDocument->getAiValidation()
->validationReason?->en
        );
        $this->assertEquals(
            'La validation IA a détecté du contenu inapproprié',
            $updatedDocument->getAiValidation()
->validationReason?->fr
        );
        $this->assertTrue($updatedDocument->isAiValidated());
    }

    public function testEnrichNonExistentDocumentThrowsException(): void
    {
        // Arrange
        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 85,
            validationReason: new ValidationReason('Valid', 'Valide'),
            processedAt: new \DateTimeImmutable(),
            referenceSubject: 'Test Subject'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'non-existent-document',
            aiValidation: $aiValidation
        );

        // Act & Assert
        $this->expectException(DocumentNotFoundException::class);

        ($this->handler)($action);
    }

    public function testUpdateExistingAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-doc-3',
            title: 'Document with Initial Validation',
            content: 'This document already has AI validation that will be updated.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(en: 'Initial validation', fr: 'Validation initiale'),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $initialValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 45,
            validationReason: new ValidationReason(
                en: 'Initial AI validation uncertain',
                fr: 'Validation IA initiale incertaine'
            ),
            processedAt: new \DateTimeImmutable('2024-01-15 16:00:00'),
            referenceSubject: 'Initial Subject'
        );
        $document->setAiValidation($initialValidation);

        $this->documentGateway->save($document);

        $updatedValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 88,
            validationReason: new ValidationReason(
                en: 'Updated AI validation completed',
                fr: 'Validation IA mise à jour terminée'
            ),
            processedAt: new \DateTimeImmutable('2024-01-15 16:30:00'),
            referenceSubject: 'Updated Subject'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-doc-3',
            aiValidation: $updatedValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-doc-3');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(88, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals(
            'Updated AI validation completed',
            $updatedDocument->getAiValidation()
->validationReason?->en
        );
        $this->assertEquals(
            'Validation IA mise à jour terminée',
            $updatedDocument->getAiValidation()
->validationReason?->fr
        );
        $this->assertTrue($updatedDocument->isAiValidated());
    }

    public function testHandlesInvalidDocumentData(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-doc-4',
            title: 'Document with Complex Data',
            content: 'This document contains complex data for serialization testing.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document with complex data',
                fr: 'Document avec données complexes'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'Complex validation with special characters: éàçù & "quotes" and <tags>',
            fr: 'Validation complexe avec caractères spéciaux: éàçù & "guillemets" et <balises>'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 95,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 16:45:00'),
            referenceSubject: 'Complex Subject with Special Characters: éàçù & "quotes"'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-doc-4',
            aiValidation: $aiValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-doc-4');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::VALIDATED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(95, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertEquals(
            'Complex validation with special characters: éàçù & "quotes" and <tags>',
            $updatedDocument->getAiValidation()
->validationReason?->en
        );
        $this->assertEquals(
            'Validation complexe avec caractères spéciaux: éàçù & "guillemets" et <balises>',
            $updatedDocument->getAiValidation()
->validationReason?->fr
        );
        $this->assertEquals(
            'Complex Subject with Special Characters: éàçù & "quotes"',
            $updatedDocument->getAiValidation()
->referenceSubject
        );
    }

    public function testEnrichDocumentWithUncertainAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-doc-5',
            title: 'Uncertain Document',
            content: 'This document has uncertain relevance and needs manual review.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document pending AI validation',
                fr: 'Document en attente de validation IA'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationReason = new ValidationReason(
            en: 'AI validation detected uncertain relevance',
            fr: 'La validation IA a détecté une pertinence incertaine'
        );

        $aiValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 65,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 15:30:00'),
            referenceSubject: 'Uncertain Content'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-doc-5',
            aiValidation: $aiValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-doc-5');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::UNCERTAIN, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(65, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertTrue($updatedDocument->isAiValidationUncertain());
        $this->assertFalse($updatedDocument->isAiValidated());
        $this->assertFalse($updatedDocument->isAiRejected());
    }

    public function testEnrichDocumentWithFailedAIValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-doc-6',
            title: 'Failed Document',
            content: 'This document failed AI processing due to technical issues.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document pending AI validation',
                fr: 'Document en attente de validation IA'
            ),
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
            status: AiValidationStatus::REJECTED,
            confidenceScore: 0,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable('2024-01-15 16:00:00'),
            referenceSubject: 'Failed Processing'
        );

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-doc-6',
            aiValidation: $aiValidation
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-doc-6');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::REJECTED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertTrue($updatedDocument->isAiRejected());
        $this->assertFalse($updatedDocument->isAiValidated());
        $this->assertFalse($updatedDocument->isAiValidationUncertain());
    }

    public function testAiValidationConfidenceScoreValidValues(): void
    {
        $validationReason = new ValidationReason(fr: 'Test de validation', en: 'Validation test');

        $validScores = [30, 60, 85];

        foreach ($validScores as $score) {
            $status = match ($score) {
                30 => AiValidationStatus::UNCERTAIN,
                60 => AiValidationStatus::UNCERTAIN,
                85 => AiValidationStatus::VALIDATED,
            };

            $aiValidation = new AIValidation(
                status: $status,
                confidenceScore: $score,
                validationReason: $validationReason,
                processedAt: new \DateTimeImmutable(),
                referenceSubject: 'Test subject'
            );

            $this->assertEquals($score, $aiValidation->confidenceScore);
        }
    }

    public function testAiValidationConfidenceScoreInvalidNegative(): void
    {
        $validationReason = new ValidationReason(fr: 'Test de validation', en: 'Validation test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence score must be between 0 and 100');

        new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: -1,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: 'Test subject'
        );
    }

    public function testAiValidationConfidenceScoreInvalidOverMaximum(): void
    {
        $validationReason = new ValidationReason(fr: 'Test de validation', en: 'Validation test');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence score must be between 0 and 100');

        new AIValidation(
            status: AiValidationStatus::VALIDATED,
            confidenceScore: 101,
            validationReason: $validationReason,
            processedAt: new \DateTimeImmutable(),
            referenceSubject: 'Test subject'
        );
    }

    public function testEnrichDocumentWithValidationError(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-error-doc',
            title: 'Document with Validation Error',
            content: 'This document encountered an error during AI validation.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document pending AI validation',
                fr: 'Document en attente de validation IA'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationError = 'Authorization failed - please check your credentials';
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-error-doc',
            validationError: $validationError
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-error-doc');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
        $this->assertFalse($updatedDocument->isAiValidated());
        $this->assertFalse($updatedDocument->isAiValidationUncertain());
    }

    public function testEnrichDocumentWithValidationErrorWithExistingAiValidation(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-error-existing-doc',
            title: 'Document with Existing Validation and Error',
            content: 'This document has existing AI validation that will be replaced by error.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document with existing validation',
                fr: 'Document avec validation existante'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );

        $existingAiValidation = new AIValidation(
            status: AiValidationStatus::UNCERTAIN,
            confidenceScore: 50,
            validationReason: new ValidationReason(
                en: 'Previous validation attempt',
                fr: 'Tentative de validation précédente'
            ),
            processedAt: new \DateTimeImmutable('-1 hour'),
            referenceSubject: 'Previous subject'
        );
        $document->setAiValidation($existingAiValidation);
        $this->documentGateway->save($document);

        $validationError = 'Network timeout during validation process';
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-error-existing-doc',
            validationError: $validationError
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-error-existing-doc');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
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
        ($this->handler)($action);
    }

    public function testValidationErrorWithSpecialCharacters(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-special-chars-error',
            title: 'Document with Special Characters Error',
            content: 'This document has an error with special characters.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document with special error',
                fr: 'Document avec erreur spéciale'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $validationError = 'Error with special characters: éàçù & "quotes" and <tags> - Network timeout: 500ms';
        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-special-chars-error',
            validationError: $validationError
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-special-chars-error');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
    }

    public function testValidationErrorWithEmptyString(): void
    {
        // Arrange
        $document = new Document(
            id: 'integration-test-empty-error',
            title: 'Document with Empty Error',
            content: 'This document has an empty error message.',
            status: DocumentStatus::PENDING,
            validationReason: new ValidationReason(
                en: 'Document with empty error',
                fr: 'Document avec erreur vide'
            ),
            dateCollect: new \DateTimeImmutable(),
            datePublish: new \DateTimeImmutable(),
            excerpt: 'Test excerpt',
            type: 'article'
        );
        $this->documentGateway->save($document);

        $action = new EnrichDocumentWithAiValidationAction(
            documentId: 'integration-test-empty-error',
            validationError: ''
        );

        // Act
        ($this->handler)($action);

        // Assert
        $updatedDocument = $this->documentGateway->get('integration-test-empty-error');
        $this->assertNotNull($updatedDocument->getAiValidation());
        $this->assertEquals(AiValidationStatus::FAILED, $updatedDocument->getAiValidation()->status);
        $this->assertEquals(0, $updatedDocument->getAiValidation()->confidenceScore);
        $this->assertNull($updatedDocument->getAiValidation()->validationReason);
        $this->assertNull($updatedDocument->getAiValidation()->referenceSubject);
    }
}
