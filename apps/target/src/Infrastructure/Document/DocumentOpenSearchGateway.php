<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Domain\Document\Deduplication\FingerprintGatewayInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Infrastructure\Serializer\DocumentDenormalizer;
use App\Infrastructure\Serializer\DocumentNormalizer;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class DocumentOpenSearchGateway implements DocumentGatewayInterface, FingerprintGatewayInterface
{
    private const int COMPOSITE_BATCH_SIZE = 1000;

    /**
     * `_source` fields excluded from the dedup-pipeline lookups. The dedup
     * stages compare documents on `id` + `fingerprint` (+ `canonicalUrl`)
     * only — the body/summary/highlight payloads would otherwise be loaded
     * into PHP for nothing on every match candidate.
     */
    private const array DEDUP_SOURCE_EXCLUDES = ['content', 'summary', 'highlight', 'insight'];

    public function __construct(
        private Client $openSearch,
        private DenormalizerInterface $denormalizer,
        #[Autowire(service: DocumentNormalizer::class)]
        private NormalizerInterface $normalizer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    private function getIndex(): string
    {
        return Document::INDEX_NAME;
    }

    public function get(string $id): Document
    {
        try {
            $responseArray = $this->openSearch->get([
                'index' => $this->getIndex(),
                'id' => $id,
            ]);
        } catch (Missing404Exception $e) {
            throw new DocumentNotFoundException(\sprintf('Document with ID "%s" not found in OpenSearch.', $id));
        }

        if (!($responseArray['found'] ?? false)) {
            throw new DocumentNotFoundException(\sprintf('Document with ID "%s" not found in index.', $id));
        }

        $source = $responseArray['_source'] ?? null;
        if (!\is_array($source)) {
            throw new DocumentNotFoundException(\sprintf('Document with ID "%s" has invalid source data.', $id));
        }

        if (!isset($source['id'])) {
            throw new \LogicException('Missing document ID in source');
        }

        /** @var Document $document */
        $document = $this->denormalizer->denormalize($source, Document::class);

        return $document;
    }

    public function save(Document $document): void
    {
        /** @var array<string, mixed> $body */
        $body = $this->normalizer->normalize($document);
        $params = [
            'index' => $this->getIndex(),
            'id' => $document->getId(),
            'body' => $body,
        ];

        $organisationId = $document->getOrganisationId();
        if (null !== $organisationId && '' !== $organisationId) {
            $params['routing'] = $organisationId;
        }

        $this->openSearch->index($params);
    }

    /**
     * @param array<int, Document> $documents
     */
    public function saveBulk(array $documents): void
    {
        if (empty($documents)) {
            return;
        }

        $params = [
            'body' => [],
        ];

        foreach ($documents as $document) {
            $action = [
                '_index' => $this->getIndex(),
                '_id' => $document->getId(),
            ];

            $organisationId = $document->getOrganisationId();
            if (null !== $organisationId && '' !== $organisationId) {
                $action['routing'] = $organisationId;
            }

            $params['body'][] = [
                'index' => $action,
            ];

            /** @var array<string, mixed> $body */
            $body = $this->normalizer->normalize($document);
            $params['body'][] = $body;
        }

        $this->openSearch->bulk($params);
    }

    public function markEventsAsExtracted(string $documentId, bool $extracted): void
    {
        $this->openSearch->update([
            'index' => $this->getIndex(),
            'id' => $documentId,
            'body' => [
                'doc' => [
                    'eventsExtracted' => $extracted,
                    'eventsExtractedAt' => new \DateTimeImmutable()
->format('c'),
                ],
            ],
        ]);
    }

    public function countDocumentsForWatchFile(string $watchFileId): int
    {
        try {
            $response = $this->openSearch->count([
                'index' => $this->getIndex(),
                'body' => [
                    'query' => [
                        'term' => [
                            'watchFile.id' => [
                                'value' => $watchFileId,
                            ],
                        ],
                    ],
                ],
            ]);

            $responseArray = $response;

            return (int) ($responseArray['count'] ?? 0);
        } catch (Missing404Exception $e) {
            $this->logger?->error('Failed to count documents for WatchFile', [
                'watch_file_id' => $watchFileId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        } catch (\Exception $e) {
            $this->logger?->error('Unexpected error while counting documents for WatchFile', [
                'watch_file_id' => $watchFileId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function countDocumentsPerWatchFile(): array
    {
        $watchFileIdsWithCounts = [];
        $afterKey = null;

        try {
            while (true) {
                $body = [
                    'size' => 0,
                    'aggs' => [
                        'watch_files' => [
                            'composite' => [
                                'size' => self::COMPOSITE_BATCH_SIZE,
                                'sources' => [
                                    [
                                        'watch_file' => [
                                            'terms' => [
                                                'field' => 'watchFile.id',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                if (null !== $afterKey) {
                    $body['aggs']['watch_files']['composite']['after'] = $afterKey;
                }

                $responseArray = $this->openSearch->search([
                    'index' => $this->getIndex(),
                    'body' => $body,
                ]);

                $buckets = $responseArray['aggregations']['watch_files']['buckets'] ?? [];
                foreach ($buckets as $bucket) {
                    $docCount = (int) ($bucket['doc_count'] ?? 0);
                    $watchFileId = $bucket['key']['watch_file'] ?? null;
                    if (\is_string($watchFileId) && '' !== $watchFileId) {
                        $watchFileIdsWithCounts[$watchFileId] = $docCount;
                    }
                }

                $afterKey = $responseArray['aggregations']['watch_files']['after_key'] ?? null;
                if (null === $afterKey) {
                    break;
                }
            }
        } catch (Missing404Exception $e) {
            $this->logger?->error('Failed to query OpenSearch for document count per WatchFile', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [];
        } catch (\Exception $e) {
            $this->logger?->error('Unexpected error while counting documents per WatchFile', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $watchFileIdsWithCounts;
    }

    public function findWatchFilesExceedingDocumentQuota(int $quota): array
    {
        $allCounts = $this->countDocumentsPerWatchFile();
        $exceeding = [];

        foreach ($allCounts as $watchFileId => $documentCount) {
            if ($documentCount > $quota) {
                $exceeding[$watchFileId] = $documentCount;
            }
        }

        return $exceeding;
    }

    public function findByCanonicalUrl(string $canonicalUrl, ?string $excludeDocumentId = null): ?Document
    {
        return $this->findOneByTerm('canonicalUrl', $canonicalUrl, $excludeDocumentId, 'canonicalUrl');
    }

    public function findByContentHash(string $contentHash, ?string $excludeDocumentId = null): ?Document
    {
        return $this->findOneByTerm(
            'fingerprint.contentHash',
            $contentHash,
            $excludeDocumentId,
            'fingerprint contentHash',
            dedupContext: true,
        );
    }

    public function findBySimHash(string $simHash, ?string $excludeDocumentId = null): array
    {
        return $this->findManyByTerm(
            'fingerprint.simHash',
            $simHash,
            $excludeDocumentId,
            limit: 50,
            logLabel: 'fingerprint simHash',
            dedupContext: true,
        );
    }

    public function findByLshBands(array $bandHashes, ?string $excludeDocumentId = null, int $limit = 50): array
    {
        if (empty($bandHashes)) {
            return [];
        }

        // `terms` over `should/[term×N]` for the LSH disjunction: smaller payload,
        // no per-clause scoring overhead, immune to `bool.max_clause_size`.
        // `constant_score` skips BM25 — we re-verify via Jaccard on candidates.
        $termsClause = [
            'terms' => [
                'fingerprint.lshBands' => $bandHashes,
            ],
        ];

        $query = null === $excludeDocumentId
            ? [
                'constant_score' => [
                    'filter' => $termsClause,
                ],
            ]
            : [
                'bool' => [
                    'filter' => $termsClause,
                    'must_not' => [
                        'ids' => [
                            'values' => [$excludeDocumentId],
                        ],
                    ],
                ],
            ];

        return $this->searchAndDenormalize(
            $query,
            $limit,
            'fingerprint lshBands',
            [
                'band_count' => \count($bandHashes),
            ],
            dedupContext: true,
        );
    }

    public function findByTitleShingles(array $titleShingles, ?string $excludeDocumentId = null, int $limit = 50): array
    {
        if (empty($titleShingles)) {
            return [];
        }

        // Same shape as findByLshBands: a `terms` disjunction is the
        // cheapest fan-out for "share at least one shingle". Caller
        // (DuplicateDetector::matchByTitleShingles) re-verifies with an
        // exact set-Jaccard on the candidates' titleShingles list.
        $termsClause = [
            'terms' => [
                'fingerprint.titleShingles' => $titleShingles,
            ],
        ];

        $query = null === $excludeDocumentId
            ? [
                'constant_score' => [
                    'filter' => $termsClause,
                ],
            ]
            : [
                'bool' => [
                    'filter' => $termsClause,
                    'must_not' => [
                        'ids' => [
                            'values' => [$excludeDocumentId],
                        ],
                    ],
                ],
            ];

        return $this->searchAndDenormalize(
            $query,
            $limit,
            'fingerprint titleShingles',
            [
                'shingle_count' => \count($titleShingles),
            ],
            dedupContext: true,
        );
    }

    /**
     * Single-hit lookup: term query on a keyword field, optional id exclusion,
     * size=1, returns the first matching `Document` or null.
     */
    private function findOneByTerm(
        string $field,
        string $value,
        ?string $excludeDocumentId,
        string $logLabel,
        bool $dedupContext = false,
    ): ?Document {
        $documents = $this->findManyByTerm(
            $field,
            $value,
            $excludeDocumentId,
            limit: 1,
            logLabel: $logLabel,
            dedupContext: $dedupContext,
        );

        return $documents[0] ?? null;
    }

    /**
     * Multi-hit lookup: same term query as {@see self::findOneByTerm()}, with a
     * caller-controlled size, returns a (possibly empty) `Document` list.
     *
     * @return list<Document>
     */
    private function findManyByTerm(
        string $field,
        string $value,
        ?string $excludeDocumentId,
        int $limit,
        string $logLabel,
        bool $dedupContext = false,
    ): array {
        // Filter context (vs query/must) skips BM25 scoring — the search is a
        // simple existence check, ranking is irrelevant.
        $termClause = [
            'term' => [
                $field => [
                    'value' => $value,
                ],
            ],
        ];

        $query = null === $excludeDocumentId
            ? [
                'constant_score' => [
                    'filter' => $termClause,
                ],
            ]
            : [
                'bool' => [
                    'filter' => $termClause,
                    'must_not' => [
                        'ids' => [
                            'values' => [$excludeDocumentId],
                        ],
                    ],
                ],
            ];

        return $this->searchAndDenormalize(
            $query,
            $limit,
            $logLabel,
            [
                'field' => $field,
            ],
            dedupContext: $dedupContext,
        );
    }

    /**
     * Run an OpenSearch search and denormalize the hits to `Document[]`.
     * Errors are logged with a human-readable `$logLabel` plus optional
     * structured context, then degraded to `[]` so callers stay simple.
     *
     * Dedup callers can opt into:
     * - excluding heavyweight body fields (`content` / `summary` / …) from
     *   the `_source` payload — these are useless to the pipeline and
     *   would otherwise load megabytes of HTML per match candidate;
     * - the batch-hydration path of {@see DocumentDenormalizer::denormalizeBatch()}
     *   which collapses the per-document `EntityManager::find()` calls for
     *   `actor` / `source` / `watchFile` / `updatedBy` into one bulk
     *   `findBy()` per type. A 50-hit response goes from ~200 SQL queries
     *   to 4.
     *
     * @param array<string, mixed> $query      OpenSearch query DSL fragment
     * @param array<string, mixed> $logContext extra structured-log fields
     *
     * @return list<Document>
     */
    private function searchAndDenormalize(
        array $query,
        int $size,
        string $logLabel,
        array $logContext = [],
        bool $dedupContext = false,
    ): array {
        $body = [
            'query' => $query,
            'size' => $size,
        ];
        if ($dedupContext) {
            $body['_source'] = [
                'excludes' => self::DEDUP_SOURCE_EXCLUDES,
            ];
        }

        try {
            $response = $this->openSearch->search([
                'index' => $this->getIndex(),
                'body' => $body,
            ]);

            /** @var list<array<string, mixed>> $hits */
            $hits = $response['hits']['hits'] ?? [];

            return $this->denormalizeHits($hits);
        } catch (Missing404Exception $e) {
            $this->logger?->error(\sprintf('Failed to query %s in OpenSearch', $logLabel), [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);

            return [];
        } catch (\Exception $e) {
            $this->logger?->error(\sprintf('Unexpected error while querying %s', $logLabel), [
                ...$logContext,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $hits
     *
     * @return list<Document>
     */
    private function denormalizeHits(array $hits): array
    {
        $sources = [];
        foreach ($hits as $hit) {
            $source = $hit['_source'] ?? null;
            if (!\is_array($source) || !isset($source['id'])) {
                continue;
            }
            $sources[] = $source;
        }

        if (empty($sources)) {
            return [];
        }

        // Batch hydration: one `findBy()` per entity class instead of N×4
        // `find()` calls. Falls back to a per-source loop on the bare
        // `DenormalizerInterface` (test stubs, alternate impls).
        if ($this->denormalizer instanceof DocumentDenormalizer) {
            return $this->denormalizer->denormalizeBatch($sources);
        }

        $documents = [];
        foreach ($sources as $source) {
            /** @var Document $document */
            $document = $this->denormalizer->denormalize($source, Document::class);
            $documents[] = $document;
        }

        return $documents;
    }

    public function findByProviderId(string $providerId): ?Document
    {
        return $this->findOneByTerm('providerId', $providerId, null, 'providerId');
    }

    public function findStaleAiValidationDocuments(\DateTimeImmutable $cutoff, int $limit): array
    {
        $response = $this->openSearch->search([
            'index' => Document::INDEX_NAME,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            [
                                'term' => [
                                    'aiValidation.status' => 'pending',
                                ],
                            ],
                            [
                                'range' => [
                                    'aiValidation.processedAt' => [
                                        'lte' => $cutoff->format('c'),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'size' => $limit,
                'sort' => [[
                    'aiValidation.processedAt' => 'asc',
                ]],
                '_source' => ['title', 'aiValidation.processedAt', 'watchFile.id'],
            ],
        ]);

        $documents = [];
        foreach ($response['hits']['hits'] ?? [] as $hit) {
            $source = $hit['_source'] ?? [];
            $documents[] = [
                'id' => $hit['_id'],
                'title' => $source['title'] ?? 'Untitled',
                'processedAt' => $source['aiValidation']['processedAt'] ?? '-',
                'watchFileId' => $source['watchFile']['id'] ?? null,
            ];
        }

        return $documents;
    }

    public function findAllIds(?string $watchFileId = null, int $limit = 500, ?string $searchAfter = null): array
    {
        $query = null !== $watchFileId
            ? [
                'term' => [
                    'watchFile.id' => $watchFileId,
                ],
            ]
            : [
                'match_all' => (object) [],
            ];

        $body = [
            'query' => $query,
            'size' => $limit,
            'sort' => [[
                '_id' => 'asc',
            ]],
            '_source' => false,
        ];

        // search_after avoids the 10k max_result_window limit of from+size pagination.
        if (null !== $searchAfter) {
            $body['search_after'] = [$searchAfter];
        }

        $response = $this->openSearch->search([
            'index' => Document::INDEX_NAME,
            'body' => $body,
        ]);

        return array_values(array_map(static fn (array $hit) => $hit['_id'], $response['hits']['hits'] ?? []));
    }
}
