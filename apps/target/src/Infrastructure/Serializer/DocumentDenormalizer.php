<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use ApiPlatform\Metadata\ApiResource;
use App\Domain\Document\Deduplication\DuplicateAttempt;
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

    /**
     * Context key used by {@see self::denormalizeBatch()} to ferry the
     * batch-loaded `#[ApiResource]` entities to {@see self::setApiResourceProperty()}.
     * Shape: `class-string => array<string, object>` (id → entity).
     */
    public const string PRELOADED_ENTITIES_KEY = 'document.preloaded_entities';

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
     * Denormalize many `_source` payloads in one shot, batch-loading every
     * `#[ApiResource]` related entity (actor, source, watchFile, updatedBy)
     * with one `findBy(['id' => $ids])` per type instead of N times one
     * `find()` per document × per property.
     *
     * Caller responsibility: pass the array of `_source` arrays (already
     * extracted from `_source`/highlight wrapping).
     *
     * @param list<array<string, mixed>> $sources `_source` payloads
     * @param array<string, mixed>       $context standard Symfony Serializer context
     *
     * @return list<Document>
     */
    public function denormalizeBatch(array $sources, ?string $format = null, array $context = []): array
    {
        if (empty($sources)) {
            return [];
        }

        $idsByClass = $this->collectApiResourceIds($sources);

        $preloaded = [];
        foreach ($idsByClass as $entityClass => $ids) {
            if (empty($ids)) {
                continue;
            }

            try {
                /** @var list<object> $entities */
                $entities = $this->entityManager->getRepository($entityClass)
                    ->findBy([
                        'id' => array_values(array_unique($ids)),
                    ]);
            } catch (\Exception $e) {
                $this->logger?->warning(\sprintf(
                    'Batch hydration failed for %s: %s — falling back to per-id lookups',
                    $entityClass,
                    $e->getMessage(),
                ));

                continue;
            }

            $byId = [];
            foreach ($entities as $entity) {
                $id = $this->propertyAccessor->getValue($entity, 'id');
                if (\is_string($id) || \is_int($id)) {
                    $byId[(string) $id] = $entity;
                }
            }
            $preloaded[$entityClass] = $byId;
        }

        $batchContext = array_merge($context, [
            self::PRELOADED_ENTITIES_KEY => $preloaded,
        ]);

        $documents = [];
        foreach ($sources as $source) {
            /** @var Document $document */
            $document = $this->denormalize($source, Document::class, $format, $batchContext);
            $documents[] = $document;
        }

        return $documents;
    }

    /**
     * Walk every payload and collect entity IDs grouped by `#[ApiResource]`
     * class — used by {@see self::denormalizeBatch()} to issue one bulk
     * `findBy()` per type.
     *
     * @param list<array<string, mixed>> $sources
     *
     * @return array<class-string, list<string>>
     */
    private function collectApiResourceIds(array $sources): array
    {
        $idsByClass = [];

        foreach ($this->getDocumentOpenSearchProperties() as $property) {
            $propertyType = $property->getType();
            if (!$this->isApiResourceProperty($propertyType)) {
                continue;
            }
            \assert($propertyType instanceof \ReflectionNamedType);
            /** @var class-string $entityClass */
            $entityClass = $propertyType->getName();
            $propertyName = $property->getName();

            $ids = [];
            foreach ($sources as $source) {
                $entry = $source[$propertyName] ?? null;
                if (!\is_array($entry)) {
                    continue;
                }
                $candidate = $entry['id'] ?? null;
                if (\is_string($candidate) && '' !== $candidate) {
                    $ids[] = $candidate;
                }
            }

            if ([] !== $ids) {
                $idsByClass[$entityClass] = $ids;
            }
        }

        return $idsByClass;
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
            $this->setApiResourceProperty($document, $property, $data, $context);
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
        } elseif ('duplicates' === $propertyName && \is_array($value)) {
            // `Document::$duplicates` is typed `array` (builtin), so the
            // generic non-builtin branch above does not fire. Map each
            // entry to a `DuplicateAttempt` value object explicitly.
            $value = array_map(
                fn (mixed $item): DuplicateAttempt => $this->denormalizer->denormalize(
                    $item,
                    DuplicateAttempt::class,
                    $format,
                    $context,
                ),
                $value,
            );
        }

        $this->setPropertyValueSafely($document, $property, $value);
    }

    /**
     * Hydrate an`#[ApiResource]`-typed property (actor, source, watchFile,
     * updatedBy…) from the OpenSearch `_source` payload.
     *
     * Bulk callers ({@see self::denormalizeBatch()}) seed `$context` with a
     * `preloaded_entities` map (`class-string => array<id, entity>`) so the
     * lookup hits an in-memory cache instead of triggering one
     * `EntityManager::find()` per property per hit (N+1 on bulk reads).
     *
     * Single-document callers omit the context — the fallback to
     * `EntityManager::find()` keeps the original O(1)-per-property
     * behaviour intact.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $context
     */
    private function setApiResourceProperty(
        Document $document,
        \ReflectionProperty $property,
        array $data,
        array $context = [],
    ): void {
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

        $preloaded = $context[self::PRELOADED_ENTITIES_KEY] ?? null;
        if (
            (\is_string($entityId) || \is_int($entityId))
            && \is_array($preloaded)
            && isset($preloaded[$entityClass])
            && \is_array($preloaded[$entityClass])
        ) {
            $entity = $preloaded[$entityClass][(string) $entityId] ?? null;
            if (null !== $entity) {
                $this->setPropertyValueSafely($document, $property, $entity);

                return;
            }
            // Fall through to per-id `find()` if the batch loader missed
            // (e.g. entity created between the bulk fetch and denormalize).
        }

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
