<?php

declare(strict_types=1);

namespace App\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\Exception\DocumentNotFoundException;
use App\Infrastructure\Serializer\DocumentNormalizer;
use OpenSearch\Client;
use OpenSearch\Common\Exceptions\Missing404Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

readonly class DocumentOpenSearchGateway implements DocumentGatewayInterface
{
    private const int COMPOSITE_BATCH_SIZE = 1000;

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

    public function findByProviderId(string $providerId): ?Document
    {
        try {
            $response = $this->openSearch->search([
                'index' => $this->getIndex(),
                'body' => [
                    'query' => [
                        'term' => [
                            'providerId' => [
                                'value' => $providerId,
                            ],
                        ],
                    ],
                    'size' => 1,
                ],
            ]);

            $responseArray = $response;

            $hits = $responseArray['hits']['hits'] ?? [];
            if (empty($hits)) {
                return null;
            }

            $source = $hits[0]['_source'] ?? null;
            if (!\is_array($source)) {
                return null;
            }

            if (!isset($source['id'])) {
                throw new \LogicException('Missing document ID in source');
            }

            /** @var Document $document */
            $document = $this->denormalizer->denormalize($source, Document::class);

            return $document;
        } catch (Missing404Exception $e) {
            $this->logger?->error('Failed to find document by providerId in OpenSearch', [
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            $this->logger?->error('Unexpected error while finding document by providerId', [
                'provider_id' => $providerId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
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
