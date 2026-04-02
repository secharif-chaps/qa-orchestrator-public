<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use ApiPlatform\Metadata\ApiResource;
use App\Domain\WatchFileEvent\DocumentLink;
use App\Domain\WatchFileEvent\EventActor;
use App\Domain\WatchFileEvent\WatchFileEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\Exception\ExceptionInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Custom denormalizer for WatchfileEvent entity to handle deserialization from OpenSearch.
 *
 * This denormalizer specifically handles the case where WatchfileEvent needs to be deserialized
 * from serialized data (e.g., from OpenSearch) and fetches nested entities from the database
 * using their IDs via the appropriate gateways.
 */
class WatchfileEventDenormalizer implements DenormalizerInterface, DenormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    public const string SERIALIZATION_GROUP = 'watch_file_event:save';

    /** @var array<string, \ReflectionProperty>|null */
    private ?array $cachedProperties = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): WatchFileEvent
    {
        if (!\is_array($data)) {
            throw new \InvalidArgumentException('WatchfileEvent denormalization requires array data');
        }

        if (isset($data['_source']) && \is_array($data['_source'])) {
            $data = $data['_source'];
        }

        // Create watchfile event instance and populate properties
        $reflectionClass = new \ReflectionClass(WatchFileEvent::class);
        $watchfileEvent = $reflectionClass->newInstanceWithoutConstructor();

        foreach ($this->getWatchfileEventOpenSearchProperties() as $property) {
            $this->setPropertyValue($watchfileEvent, $property, $data, $format, $context);
        }

        // Sort actors alphabetically by name
        $actors = $watchfileEvent->getActors();
        if ($actors && !$actors->isEmpty()) {
            $actorsArray = $actors->toArray();
            usort($actorsArray, fn (EventActor $a, EventActor $b) => strcasecmp($a->getName(), $b->getName()));
            $watchfileEvent->setActors(new ArrayCollection($actorsArray));
        }

        return $watchfileEvent;
    }

    /**
     * @return array<string, \ReflectionProperty>
     */
    private function getWatchfileEventOpenSearchProperties(): array
    {
        if (null !== $this->cachedProperties) {
            return $this->cachedProperties;
        }

        $reflectionClass = new \ReflectionClass(WatchFileEvent::class);
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
        WatchFileEvent $watchfileEvent,
        \ReflectionProperty $property,
        array $data,
        ?string $format,
        array $context,
    ): void {
        $propertyType = $property->getType();

        if ($this->isApiResourceProperty($propertyType)) {
            $this->setApiResourceProperty($watchfileEvent, $property, $data);
        } else {
            $this->setSimpleProperty($watchfileEvent, $property, $data, $format, $context);
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
        WatchFileEvent $watchfileEvent,
        \ReflectionProperty $property,
        array $data,
        ?string $format,
        array $context,
    ): void {
        $propertyName = $property->getName();
        $propertyType = $property->getType();
        $isNullable = $propertyType instanceof \ReflectionNamedType && $propertyType->allowsNull();

        if (!\array_key_exists($propertyName, $data)) {
            if ($isNullable) {
                $this->setPropertyValueSafely($watchfileEvent, $property, null);
            }

            return;
        }

        $value = $data[$propertyName];

        if ($propertyType instanceof \ReflectionNamedType && !$propertyType->isBuiltin()) {
            $targetType = $propertyType->getName();
            try {
                if ("Doctrine\Common\Collections\Collection" === $targetType) {
                    if (is_iterable($value)) {
                        $collection = new ArrayCollection();
                        $targetType = match ($propertyName) {
                            'documentLinks' => DocumentLink::class,
                            'actors' => EventActor::class,
                        };
                        foreach ($value as $item) {
                            $result = $this->denormalizer->denormalize($item, $targetType, $format, $context);
                            $collection->add($result);
                        }
                        $value = $collection;
                    }
                } else {
                    $value = $this->denormalizer->denormalize($value, $targetType, $format, $context);
                }
            } catch (\Throwable $e) {
                $this->logger?->error('WatchfileEvent denormalize error: ' . $e->getMessage());
            }
        }

        $this->setPropertyValueSafely($watchfileEvent, $property, $value);
    }

    /**
     * TODO: Performance - N+1 queries issue.
     *
     * Current implementation: 1 SQL query per ApiResource property.
     * Impact: Multiple queries per watchfile event during bulk denormalization.
     *
     * Optimization needed:
     * - Implement batch loading: group entity IDs by type and use findBy(['id' => $ids])
     * - Or add entity cache to avoid repeated queries for same entities
     *
     * @param array<string, mixed> $data
     */
    private function setApiResourceProperty(
        WatchFileEvent $watchfileEvent,
        \ReflectionProperty $property,
        array $data,
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

        try {
            $entity = $this->entityManager->find($entityClass, $entityId);
            if ($entity) {
                $this->setPropertyValueSafely($watchfileEvent, $property, $entity);
            }
        } catch (\Exception $e) {
            $this->logger?->warning(
                \sprintf(
                    'Failed to load %s entity with ID %s for watchfile event property %s: %s',
                    $entityClass,
                    \is_string($entityId) ? $entityId : '',
                    $propertyName,
                    $e->getMessage()
                )
            );
        }
    }

    private function setPropertyValueSafely(
        WatchFileEvent $watchfileEvent,
        \ReflectionProperty $property,
        mixed $value,
    ): void {
        $propertyName = $property->getName();

        try {
            if ($this->propertyAccessor->isWritable($watchfileEvent, $propertyName)) {
                $this->propertyAccessor->setValue($watchfileEvent, $propertyName, $value);
            } else {
                // Fallback to reflection for private/inaccessible properties
                $property->setAccessible(true);
                $property->setValue($watchfileEvent, $value);
            }
        } catch (ExceptionInterface $e) {
            $this->logger?->error(
                \sprintf('Failed to set property %s on WatchfileEvent: %s', $propertyName, $e->getMessage()),
                [
                    'propertyName' => $propertyName,
                    'value' => $value,
                    'watchfileEvent' => $watchfileEvent,
                    'error' => $e,
                ],
            );
        }
    }

    public function supportsDenormalization(
        mixed $data,
        string $type,
        ?string $format = null,
        array $context = [],
    ): bool {
        return WatchFileEvent::class === $type && \is_array($data);
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            WatchFileEvent::class => true,
        ];
    }
}
