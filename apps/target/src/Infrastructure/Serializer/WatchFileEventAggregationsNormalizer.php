<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Infrastructure\Pagination\WatchFileEventPaginatorWithAggregations;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Adds facets to WatchFileEvent graph responses from OpenSearch aggregations.
 */
class WatchFileEventAggregationsNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    private const string ALREADY_CALLED = 'WatchFileEventAggregationsNormalizer_ALREADY_CALLED';

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        if ($object instanceof WatchFileEventPaginatorWithAggregations) {
            // Normalize the inner paginator to get the Hydra structure
            /** @var array<string, mixed> $data */
            $data = $this->normalizer->normalize($object->paginator, $format, $context);

            // Add facets to the response, transforming to match /events format
            if (!empty($object->aggregations)) {
                $data['facets'] = $this->buildFacets($object->aggregations);
            }

            return $data;
        }

        // Fallback: normalize as-is
        /** @var array<string, mixed> $normalized */
        $normalized = $this->normalizer->normalize($object, $format, $context);

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        // Avoid infinite recursion
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof WatchFileEventPaginatorWithAggregations;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            WatchFileEventPaginatorWithAggregations::class => true,
        ];
    }

    /**
     * Transform facets from getEventFacets format to match /events format.
     *
     * @param array<string, mixed> $aggregations
     *
     * @return array<string, mixed>
     */
    private function buildFacets(array $aggregations): array
    {
        return [
            'actors' => $aggregations['actors'] ?? [],
            'eventTypes' => $aggregations['eventTypes'] ?? [],
            'dateRange' => [
                'maxStartDate' => $aggregations['maxStartDate'] ?? null,
                'maxEndDate' => $aggregations['maxEndDate'] ?? null,
            ],
        ];
    }
}
