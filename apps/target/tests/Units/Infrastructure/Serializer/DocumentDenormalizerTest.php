<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Serializer;

use App\Domain\Actor\Actor;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\ValidationReason;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Serializer\DocumentDenormalizer;
use App\Tests\Utils\EntityUtilsTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

#[AllowMockObjectsWithoutExpectations]
class DocumentDenormalizerTest extends TestCase
{
    use EntityUtilsTrait;
    private DocumentDenormalizer $denormalizer;
    private EntityManagerInterface&MockObject $entityManager;
    private PropertyAccessorInterface&MockObject $propertyAccessor;
    private DenormalizerInterface&MockObject $denormalizerInterface;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->propertyAccessor = $this->createMock(PropertyAccessorInterface::class);
        $this->denormalizerInterface = $this->createMock(DenormalizerInterface::class);

        $this->denormalizer = new DocumentDenormalizer($this->entityManager, $this->propertyAccessor);

        // Set the serializer on the denormalizer using reflection
        $this->denormalizer->setDenormalizer($this->denormalizerInterface);

        $this->setupDefaultMocks();
    }

    private function setupDefaultMocks(): void
    {
        // Mock PropertyAccessor to use reflection fallback
        $this->propertyAccessor
            ->expects($this->any())
            ->method('setValue')
            ->willThrowException(new \Exception('PropertyAccessor failed'));

        // Mock denormalizer for type conversion
        $this->denormalizerInterface
            ->expects($this->any())
            ->method('denormalize')
            ->willReturnCallback(function ($data, $type) {
                if (\DateTimeImmutable::class === $type) {
                    return new \DateTimeImmutable($data);
                }
                if (DocumentStatus::class === $type) {
                    return DocumentStatus::from($data);
                }
                if (ValidationReason::class === $type) {
                    return ValidationReason::fromArray($data);
                }

                return $data;
            });
    }

    public function testDenormalizeWithBasicData(): void
    {
        // Arrange
        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'validationReason' => [
                'en' => 'Manual validation',
                'fr' => 'Validation manuelle',
            ],
            'language' => 'en',
            'isInteresting' => true,
            'insight' => 'Test insight',
            'cfcRestricted' => false,
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('test-document-id', $document->getId());
        $this->assertEquals('Test Document Title', $document->getTitle());
        $this->assertEquals('Test document excerpt', $document->getExcerpt());
        $this->assertEquals('article', $document->getType());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T10:00:00+00:00'), $document->getDatePublish());
        $this->assertEquals(new \DateTimeImmutable('2024-01-01T11:00:00+00:00'), $document->getDateCollect());
        $this->assertEquals('Test document content', $document->getContent());
        $this->assertEquals(DocumentStatus::VALIDATED, $document->getStatus());
        $validationReason = $document->getValidationReason();
        $this->assertInstanceOf(ValidationReason::class, $validationReason);
        $this->assertEquals('Manual validation', $validationReason->en);
        $this->assertEquals('Validation manuelle', $validationReason->fr);
        $this->assertEquals('en', $document->getLanguage());
        $this->assertTrue($document->isInteresting());
        $this->assertEquals('Test insight', $document->getInsight());
        $this->assertFalse($document->isCfcRestricted());
    }

    public function testDenormalizeWithOpenSearchFormat(): void
    {
        // Arrange
        $data = [
            '_source' => [
                'id' => 'test-document-id',
                'title' => 'Test Document Title',
                'excerpt' => 'Test document excerpt',
                'type' => 'article',
                'datePublish' => '2024-01-01T10:00:00+00:00',
                'dateCollect' => '2024-01-01T11:00:00+00:00',
                'content' => 'Test document content',
                'status' => 'validated',
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('test-document-id', $document->getId());
        $this->assertEquals('Test Document Title', $document->getTitle());
    }

    public function testDenormalizeWithHighlightData(): void
    {
        // Arrange
        $highlightData = [
            'title' => ['<em>Test</em> Document Title'],
            'content' => ['Test document <em>content</em> with highlights'],
        ];

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'highlight' => $highlightData,
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals($highlightData, $document->getHighlight());
    }

    public function testDenormalizeWithNestedActor(): void
    {
        // Arrange
        $actor = new Actor('Test Actor Label', new Organisation('Test Org', 'test-org-id'));
        $actorId = $actor->getId();

        $this->entityManager
            ->expects($this->any())
            ->method('find')
            ->willReturnCallback(function ($class, $id) use ($actor, $actorId) {
                if (Actor::class === $class && $id === $actorId) {
                    return $actor;
                }

                return null;
            });

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'actor' => [
                'id' => $actorId,
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithNestedSource(): void
    {
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Test source description', en: 'Test source description'),
            SourceType::RSS_FEED,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'high', en: 'high'),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'test-source-id');
        $sourceId = $source->getId();

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('find')
            ->with(Source::class, $sourceId)
            ->willReturn($source);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'source' => [
                'id' => $sourceId,
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithNestedWatchFile(): void
    {
        // Arrange
        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'test-watchfile-id');
        $watchFileId = $watchFile->getId();

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('find')
            ->with(WatchFile::class, $watchFileId)
            ->willReturn($watchFile);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'watchFile' => [
                'id' => $watchFileId,
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithNestedUser(): void
    {
        // Arrange
        $user = new User('test@example.com', 'Test User');
        $userId = $user->getId();

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('find')
            ->with(User::class, $userId)
            ->willReturn($user);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'updatedBy' => [
                'id' => $userId,
            ],
            'updatedAt' => '2024-01-01T12:00:00+00:00',
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithAllNestedEntities(): void
    {
        // Arrange
        $actor = new Actor('Test Actor Label', new Organisation('Test Org', 'test-org-id'));
        $actorId = $actor->getId();

        $watchFile = new WatchFile('Test Watch File', 'Test Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'test-watchfile-id');
        $watchFileId = $watchFile->getId();

        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Test source description', en: 'Test source description'),
            SourceType::RSS_FEED,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'high', en: 'high'),
            null,
            $watchFile
        );
        $this->forcePropertyValue($source, 'test-source-id');
        $sourceId = $source->getId();

        $user = new User('test@example.com', 'Test User');
        $userId = $user->getId();

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('find')
            ->willReturnMap([
                [Actor::class, $actorId, $actor],
                [Source::class, $sourceId, $source],
                [WatchFile::class, $watchFileId, $watchFile],
                [User::class, $userId, $user],
            ]);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'actor' => [
                'id' => $actorId,
            ],
            'source' => [
                'id' => $sourceId,
            ],
            'watchFile' => [
                'id' => $watchFileId,
            ],
            'updatedBy' => [
                'id' => $userId,
            ],
            'updatedAt' => '2024-01-01T12:00:00+00:00',
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeHandlesActorNotFoundException(): void
    {
        // Arrange
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Actor::class, 'non-existent-actor-id')
            ->willReturn(null);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'actor' => [
                'id' => 'non-existent-actor-id',
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeHandlesSourceNotFoundException(): void
    {
        // Arrange
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(Source::class, 'non-existent-source-id')
            ->willReturn(null);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'source' => [
                'id' => 'non-existent-source-id',
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeHandlesWatchFileNotFoundException(): void
    {
        // Arrange
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(WatchFile::class, 'non-existent-watchfile-id')
            ->willReturn(null);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'watchFile' => [
                'id' => 'non-existent-watchfile-id',
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeHandlesUserNotFoundException(): void
    {
        // Arrange
        $this->entityManager
            ->expects($this->once())
            ->method('find')
            ->with(User::class, 'non-existent-user-id')
            ->willReturn(null);

        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'updatedBy' => [
                'id' => 'non-existent-user-id',
            ],
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeThrowsExceptionForNonArrayData(): void
    {
        // Arrange
        $data = 'invalid-data';

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Document denormalization requires array data');

        // Act
        $this->denormalizer->denormalize($data, Document::class);
    }

    public function testDenormalizeWithMinimalRequiredFields(): void
    {
        // Arrange
        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'validationReason' => null,
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
        $this->assertEquals('test-document-id', $document->getId());
        $this->assertEquals('Test Document Title', $document->getTitle());
    }

    public function testSupportsDenormalization(): void
    {
        // Test with Document class and array data
        $this->assertTrue($this->denormalizer->supportsDenormalization([], Document::class));

        // Test with non-Document class
        $this->assertFalse($this->denormalizer->supportsDenormalization([], 'OtherClass'));

        // Test with non-array data
        $this->assertFalse($this->denormalizer->supportsDenormalization('string', Document::class));
    }

    public function testGetSupportedTypes(): void
    {
        // Act
        $supportedTypes = $this->denormalizer->getSupportedTypes('json');

        // Assert
        $this->assertEquals([
            Document::class => true,
        ], $supportedTypes);
    }

    public function testDenormalizeWithInvalidActorData(): void
    {
        // Arrange
        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'actor' => 'invalid-actor-data', // Should be array
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithActorDataMissingId(): void
    {
        // Arrange
        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'actor' => [
                'name' => 'Test Actor',
            ], // Missing 'id' field
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithInvalidUpdatedByData(): void
    {
        // Arrange
        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'updatedBy' => 'invalid-user-data', // Should be array
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }

    public function testDenormalizeWithUpdatedByDataMissingId(): void
    {
        // Arrange
        $data = [
            'id' => 'test-document-id',
            'title' => 'Test Document Title',
            'excerpt' => 'Test document excerpt',
            'type' => 'article',
            'datePublish' => '2024-01-01T10:00:00+00:00',
            'dateCollect' => '2024-01-01T11:00:00+00:00',
            'content' => 'Test document content',
            'status' => 'validated',
            'updatedBy' => [
                'name' => 'Test User',
            ], // Missing 'id' field
        ];

        // Act
        $document = $this->denormalizer->denormalize($data, Document::class);

        // Assert
        $this->assertInstanceOf(Document::class, $document);
    }
}
