<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

/**
 * Adds facets to WatchFileEvent collection responses from OpenSearch aggregations.
 */
class WatchFileEventCollectionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    private const string ALREADY_CALLED = 'WATCH_FILE_EVENT_COLLECTION_NORMALIZER_ALREADY_CALLED';

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        if ($object instanceof PaginatorWithAggregations) {
            /** @var array<string, mixed> $data */
            $data = $this->normalizer->normalize($object->paginator, $format, $context);

            $data['facets'] = $this->buildFacets($object->aggregations ?? []);

            return $data;
        }

        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($object, $format, $context);

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        if (!$data instanceof PaginatorWithAggregations) {
            return false;
        }

        // Only handle WatchFileEvent collections
        return WatchFileEvent::class === $data->resourceClass;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PaginatorWithAggregations::class => true,
        ];
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<string, mixed>
     */
    private function buildFacets(array $aggregations): array
    {
        return [
            'actors' => $this->buildActorsFacet($aggregations),
            'eventTypes' => $this->buildEventTypesFacet($aggregations),
            'dateRange' => $this->buildDateRangeFacet($aggregations),
        ];
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildActorsFacet(array $aggregations): array
    {
        $actors = [];

        if (
            isset($aggregations['actors'])
            && \is_array($aggregations['actors'])
            && isset($aggregations['actors']['actor_ids'])
            && \is_array($aggregations['actors']['actor_ids'])
            && isset($aggregations['actors']['actor_ids']['buckets'])
        ) {
            Assert::isArray($aggregations['actors']['actor_ids']['buckets']);

            foreach ($aggregations['actors']['actor_ids']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $actorName = $bucket['key'];
                if (
                    isset($bucket['actor_name'])
                    && \is_array($bucket['actor_name'])
                    && isset($bucket['actor_name']['buckets'])
                    && \is_array($bucket['actor_name']['buckets'])
                    && !empty($bucket['actor_name']['buckets'])
                ) {
                    $nameBucket = $bucket['actor_name']['buckets'][0];
                    Assert::isArray($nameBucket, 'Name bucket must be an array');
                    if (isset($nameBucket['key']) && \is_string($nameBucket['key'])) {
                        $actorName = $nameBucket['key'];
                    }
                }

                $actors[] = [
                    'id' => $bucket['key'],
                    'name' => $actorName,
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $actors;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildEventTypesFacet(array $aggregations): array
    {
        $eventTypes = [];

        if (
            isset($aggregations['eventTypes'])
            && \is_array($aggregations['eventTypes'])
            && isset($aggregations['eventTypes']['buckets'])
        ) {
            Assert::isArray($aggregations['eventTypes']['buckets']);

            foreach ($aggregations['eventTypes']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $eventTypes[] = [
                    'type' => $bucket['key'],
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $eventTypes;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<string, mixed>
     */
    private function buildDateRangeFacet(array $aggregations): array
    {
        $dateRange = [
            'maxStartDate' => null,
            'maxEndDate' => null,
        ];

        // Extract max start date
        if (
            isset($aggregations['max_start_date'])
            && \is_array($aggregations['max_start_date'])
            && isset($aggregations['max_start_date']['value'])
        ) {
            $maxStartTimestamp = $aggregations['max_start_date']['value'];
            if (is_numeric($maxStartTimestamp)) {
                $dateRange['maxStartDate'] = new \DateTimeImmutable()
                    ->setTimestamp((int) ($maxStartTimestamp / 1000))
                    ->format('c');
            }
        }

        // Extract max end date
        if (
            isset($aggregations['max_end_date'])
            && \is_array($aggregations['max_end_date'])
            && isset($aggregations['max_end_date']['value'])
        ) {
            $maxEndTimestamp = $aggregations['max_end_date']['value'];
            if (is_numeric($maxEndTimestamp)) {
                $dateRange['maxEndDate'] = new \DateTimeImmutable()
                    ->setTimestamp((int) ($maxEndTimestamp / 1000))
                    ->format('c');
            }
        }

        return $dateRange;
    }
}
