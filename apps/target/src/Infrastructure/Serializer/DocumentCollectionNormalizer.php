<?php

declare(strict_types=1);

namespace App\Infrastructure\Serializer;

use App\Domain\Actor\ActorGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Source\SourceGatewayInterface;
use App\Infrastructure\OpenSearch\Filter\DocumentValidationStatusFilter;
use App\Infrastructure\Pagination\PaginatorWithAggregations;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Webmozart\Assert\Assert;

/**
 * Adds facets to Document collection responses from OpenSearch aggregations.
 */
class DocumentCollectionNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;
    private const string ALREADY_CALLED = 'DOCUMENT_COLLECTION_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private readonly ActorGatewayInterface $actorGateway,
        private readonly SourceGatewayInterface $sourceGateway,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        $context[self::ALREADY_CALLED] = true;

        if ($object instanceof PaginatorWithAggregations) {
            // Normalize the inner paginator to get the Hydra structure
            /** @var array<string, mixed> $data */
            $data = $this->normalizer->normalize($object->paginator, $format, $context);

            // Add facets to the response
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

        if (!$data instanceof PaginatorWithAggregations) {
            return false;
        }

        // Only handle Document collections
        return Document::class === $data->resourceClass;
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
            'sources' => $this->buildSourcesFacet($aggregations),
            'domains' => $this->buildDomainsFacet($aggregations),
            'statuses' => $this->buildStatusesFacet($aggregations),
            'validationStatuses' => $this->buildValidationStatusesFacet($aggregations),
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
            && isset($aggregations['actors']['buckets'])
        ) {
            Assert::isArray($aggregations['actors']['buckets']);

            // First pass: collect IDs that need to be fetched
            $idsToFetch = [];
            foreach ($aggregations['actors']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $actorLabel = $bucket['actor_label']['hits']['hits'][0]['_source']['actor']['label'] ?? null;

                // If doc_count is 0 and we don't have metadata, mark for fetching
                if (0 === $bucket['doc_count'] && null === $actorLabel) {
                    $idsToFetch[] = $bucket['key'];
                }
            }

            // Batch fetch actors from database
            $fetchedActors = [];
            if (!empty($idsToFetch)) {
                $fetchedActors = $this->actorGateway->findByIds($idsToFetch);
            }

            // Second pass: build the result using fetched data
            foreach ($aggregations['actors']['buckets'] as $bucket) {
                Assert::isArray($bucket);

                $actorLabel = $bucket['actor_label']['hits']['hits'][0]['_source']['actor']['label'] ?? null;
                $actorPrimaryDomain = $bucket['actor_primary_domain']['hits']['hits'][0]['_source']['actor']['primaryDomain'] ?? null;

                // If doc_count is 0 and we don't have metadata, use fetched data
                if (0 === $bucket['doc_count'] && null === $actorLabel) {
                    if (isset($fetchedActors[$bucket['key']])) {
                        $actor = $fetchedActors[$bucket['key']];
                        $actorLabel = $actor->getLabel();
                        $actorPrimaryDomain = $actor->getPrimaryDomain();
                    } else {
                        // Actor not found in database, skip it
                        continue;
                    }
                }

                $actors[] = [
                    'actor' => [
                        'id' => $bucket['key'],
                        'label' => $actorLabel,
                        'primaryDomain' => $actorPrimaryDomain,
                    ],
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
    private function buildSourcesFacet(array $aggregations): array
    {
        $sources = [];

        if (
            isset($aggregations['sources'])
            && \is_array($aggregations['sources'])
            && isset($aggregations['sources']['buckets'])
        ) {
            Assert::isArray($aggregations['sources']['buckets']);

            // First pass: collect IDs that need to be fetched
            $idsToFetch = [];
            foreach ($aggregations['sources']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $sourceName = $bucket['source_name']['hits']['hits'][0]['_source']['source']['name'] ?? null;

                // If doc_count is 0 and we don't have metadata, mark for fetching
                if (0 === $bucket['doc_count'] && null === $sourceName) {
                    $idsToFetch[] = $bucket['key'];
                }
            }

            // Batch fetch sources from database
            $fetchedSources = [];
            if (!empty($idsToFetch)) {
                $fetchedSources = $this->sourceGateway->findByIds($idsToFetch);
            }

            // Second pass: build the result using fetched data
            foreach ($aggregations['sources']['buckets'] as $bucket) {
                Assert::isArray($bucket);

                $sourceName = $bucket['source_name']['hits']['hits'][0]['_source']['source']['name'] ?? null;
                $sourcePrimaryDomain = $bucket['source_primary_domain']['hits']['hits'][0]['_source']['source']['primaryDomain'] ?? null;

                // If doc_count is 0 and we don't have metadata, use fetched data
                if (0 === $bucket['doc_count'] && null === $sourceName) {
                    if (isset($fetchedSources[$bucket['key']])) {
                        $source = $fetchedSources[$bucket['key']];
                        $sourceName = $source->getName();
                        $sourcePrimaryDomain = $source->getPrimaryDomain();
                    } else {
                        // Source not found in database, skip it
                        continue;
                    }
                }

                $sources[] = [
                    'source' => [
                        'id' => $bucket['key'],
                        'name' => $sourceName,
                        'primaryDomain' => $sourcePrimaryDomain,
                    ],
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $sources;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDomainsFacet(array $aggregations): array
    {
        $domains = [];

        if (
            isset($aggregations['domains'])
            && \is_array($aggregations['domains'])
            && isset($aggregations['domains']['buckets'])
        ) {
            Assert::isArray($aggregations['domains']['buckets']);
            foreach ($aggregations['domains']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $domains[] = [
                    'domain' => $bucket['key'],
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $domains;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildStatusesFacet(array $aggregations): array
    {
        $statuses = [];

        // Parse statuses from aggregations
        if (
            isset($aggregations['statuses'])
            && \is_array($aggregations['statuses'])
            && isset($aggregations['statuses']['buckets'])
        ) {
            Assert::isArray($aggregations['statuses']['buckets']);
            foreach ($aggregations['statuses']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $statuses[] = [
                    'status' => $bucket['key'],
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        // Add missing statuses with count 0
        $existingStatusValues = array_column($statuses, 'status');
        foreach (DocumentStatus::cases() as $status) {
            if (!\in_array($status->value, $existingStatusValues, true)) {
                $statuses[] = [
                    'status' => $status->value,
                    'count' => 0,
                ];
            }
        }

        return $statuses;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildValidationStatusesFacet(array $aggregations): array
    {
        $validationStatuses = [];

        // Process AI validation statuses
        $validationStatuses = array_merge($validationStatuses, $this->processAiValidationStatuses($aggregations));

        // Process manual validation statuses
        $validationStatuses = array_merge(
            $validationStatuses,
            $this->processManualValidationStatuses($aggregations)
        );

        return $validationStatuses;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function processAiValidationStatuses(array $aggregations): array
    {
        $statuses = [];

        if (
            isset($aggregations['aiValidationStatuses'])
            && \is_array($aggregations['aiValidationStatuses'])
            && isset($aggregations['aiValidationStatuses']['buckets'])
        ) {
            Assert::isArray($aggregations['aiValidationStatuses']['buckets']);
            foreach ($aggregations['aiValidationStatuses']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $statusKey = DocumentValidationStatusFilter::AI_PREFIX . $bucket['key'];
                $statuses[] = [
                    'status' => $statusKey,
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $statuses;
    }

    /**
     * @param array<string, mixed> $aggregations
     *
     * @return array<int, array<string, mixed>>
     */
    private function processManualValidationStatuses(array $aggregations): array
    {
        $statuses = [];

        if (
            isset($aggregations['manualValidationStatuses'])
            && \is_array($aggregations['manualValidationStatuses'])
            && isset($aggregations['manualValidationStatuses']['buckets'])
        ) {
            Assert::isArray($aggregations['manualValidationStatuses']['buckets']);
            foreach ($aggregations['manualValidationStatuses']['buckets'] as $bucket) {
                Assert::isArray($bucket);
                Assert::keyExists($bucket, 'key');
                Assert::keyExists($bucket, 'doc_count');
                Assert::integer($bucket['doc_count']);

                $statusKey = DocumentValidationStatusFilter::MANUAL_PREFIX . $bucket['key'];
                $statuses[] = [
                    'status' => $statusKey,
                    'count' => $bucket['doc_count'],
                ];
            }
        }

        return $statuses;
    }
}
