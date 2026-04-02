<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use ApiPlatform\Metadata\ApiResource;
use App\Domain\Document\Document;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Custom denormalizer for Document entity to handle deserialization from OpenSearch.
 *
 * This denormalizer specifically handles the case where Document needs to be deserialized
 * from serialized data (e.g., from OpenSearch) and fetches nested entities from the database
 * using their IDs via the appropriate gateways.
 */
class DocumentDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    public const string SERIALIZATION_GROUP = 'document:save';

    /** @var array<string, \ReflectionProperty>|null */
    private ?array $cachedProperties = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): Document
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('Document denormalization requires array data');
        }

        /** @var array<string, array<int, string>>|null $highlightData */
        $highlightData = $data['highlight'] ?? null;

        $hasSource = isset($data['_source']) && \is_array($data['_source']);
        if ($hasSource) {
            $this->logger?->debug(
                \sprintf(
                    'DocumentDenormalizer: Found _source in OpenSearch data. Keys before extraction: %s',
                    implode(', ', array_keys($data))
                )
            );
            $data = $data['_source'];
        } else {
        }

        // Create document instance and populate properties
        $reflectionClass = new \ReflectionClass(Document::class);
        $document = $reflectionClass->newInstanceWithoutConstructor();

        foreach ($this->getDocumentOpenSearchProperties() as $property) {
            $this->setPropertyValue($document, $property, $data, $format, $context);
        }

        // Handle highlighting data from OpenSearch
        if ($highlightData) {
            $document->setHighlight($highlightData);
        }

        return $document;
    }

    /**
     * @return array<string, \ReflectionProperty>
     */
    private function getDocumentOpenSearchProperties(): array
    {
        if (null !== $this->cachedProperties) {
            return $this->cachedProperties;
        }

        $reflectionClass = new \ReflectionClass(Document::class);
        $this->cachedProperties = [];

        foreach ($reflectionClass->getProperties() as $property) {
            $groupsAttribute = $property->getAttributes(Groups::class);
            if (!empty($groupsAttribute)) {
                $groups = $groupsAttribute[0]->getArguments()[0];
                if (\in_array(self::SERIALIZATION_GROUP, $groups, true)) {
                    $this->cachedProperties[$property->getName()] = $property;
                }
            }
        }

        return $this->cachedProperties;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    private function setPropertyValue(
        Document $document,
        \ReflectionProperty $property,
        array $data,
        ?string $format,
        array $context,
    ): void {
        $propertyType = $property->getType();

        if ($this->isApiResourceProperty($propertyType)) {
            $this->setApiResourceProperty($document, $property, $data);
        } else {
            $this->setSimpleProperty($document, $property, $data, $format, $context);
        }
    }

    private function isApiResourceProperty(?\ReflectionType $propertyType): bool
    {
        if (!$propertyType instanceof \ReflectionNamedType || $propertyType->isBuiltin()) {
            return false;
        }

        $typeClass = $propertyType->getName();
        /** @var class-string $typeClass */
        $typeReflection = new \ReflectionClass($typeClass);

        return !empty($typeReflection->getAttributes(ApiResource::class));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    private function setSimpleProperty(
        Document $document,
        \ReflectionProperty $property,
        array $data,
        ?string $format,
        array $context,
    ): void {
        $propertyName = $property->getName();

        if (!isset($data[$propertyName])) {
            return;
        }

        $value = $data[$propertyName];
        $propertyType = $property->getType();

        if ($propertyType instanceof \ReflectionNamedType && !$propertyType->isBuiltin()) {
            $targetType = $propertyType->getName();
            $value = $this->denormalizer->denormalize($value, $targetType, $format, $context);
        }

        $this->setPropertyValueSafely($document, $property, $value);
    }

    /**
     * TODO: Performance - N+1 queries issue.
     *
     * Current implementation: 1 SQL query per ApiResource property (actor, source, watchFile, updatedBy).
     * Impact: 4+ queries per document during bulk denormalization.
     *
     * Optimization needed:
     * - Implement batch loading: group entity IDs by type and use findBy(['id' => $ids])
     * - Or add entity cache to avoid repeated queries for same entities
     *
     * @param array<string, mixed> $data
     */
    private function setApiResourceProperty(Document $document, \ReflectionProperty $property, array $data): void
    {
        $propertyName = $property->getName();

        if (!isset($data[$propertyName]) || !\is_array($data[$propertyName]) || !isset($data[$propertyName]['id'])) {
            return;
        }

        $entityId = $data[$propertyName]['id'];
        $propertyType = $property->getType();

        if (!$propertyType instanceof \ReflectionNamedType || $propertyType->isBuiltin()) {
            return;
        }

        /** @var class-string $entityClass */
        $entityClass = $propertyType->getName();

        try {
            $entity = $this->entityManager->find($entityClass, $entityId);
            if ($entity) {
                $this->setPropertyValueSafely($document, $property, $entity);
            }
        } catch (\Exception $e) {
            $this->logger?->warning(
                \sprintf(
                    'Failed to load %s entity with ID %s for document property %s: %s',
                    $entityClass,
                    \is_string($entityId) ? $entityId : '',
                    $propertyName,
                    $e->getMessage()
                )
            );
        }
    }

    private function setPropertyValueSafely(Document $document, \ReflectionProperty $property, mixed $value): void
    {
        $propertyName = $property->getName();

        try {
            if ($this->propertyAccessor->isWritable($document, $propertyName)) {
                $this->propertyAccessor->setValue($document, $propertyName, $value);
            } else {
                // Fallback to reflection for private/inaccessible properties
                $property->setAccessible(true);
                $property->setValue($document, $value);
            }
        } catch (ExceptionInterface $e) {
            $this->logger?->error(
                \sprintf('Failed to set property %s on Document: %s', $propertyName, $e->getMessage())
            );
        }
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return Document::class === $type && \is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Document::class => true,
        ];
    }
}
