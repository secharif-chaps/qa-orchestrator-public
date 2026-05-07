<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

/**
 * Add `collectTaskId` (keyword) at the document top level.
 *
 * Each Document is now linked back to the {@see App\Domain\Collect\CollectTask}
 * that produced it. Enables the CLI `document:create --sync` recap to look up
 * the resulting Document(s) after dispatching `CreateCollectTaskAction`,
 * without piggy-backing on the messenger envelope chain. Also opens the door
 * to provider-agnostic ingestion since every provider (web, manual, apify,
 * bakus) populates the field uniformly through {@see Document::capitalizeFrom}.
 *
 * No backfill needed (no production data yet).
 */
class Version20260507000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Add collectTaskId field at document top level for provider-agnostic ingestion lookup';
    }

    public function up(): void
    {
        $this->updateIndex(Document::INDEX_NAME, [
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
                    ],
                    'providerId' => [
                        'type' => 'keyword',
                        'index' => true,
                    ],
                    // New in this migration: link Document → CollectTask that produced it.
                    // Term-queried by `findByCollectTaskId` (CLI sync recap), no scoring
                    // needed → keyword + indexed.
                    'collectTaskId' => [
                        'type' => 'keyword',
                        'index' => true,
                    ],
                    'title' => [
                        'type' => 'text',
                        'fields' => [
                            'keyword' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                    'excerpt' => [
                        'type' => 'text',
                    ],
                    'type' => [
                        'type' => 'keyword',
                    ],
                    'datePublish' => [
                        'type' => 'date',
                    ],
                    'dateCollect' => [
                        'type' => 'date',
                    ],
                    'content' => [
                        'type' => 'text',
                    ],
                    'source' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                            'name' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                    ],
                                ],
                            ],
                            'url' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                    'actor' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                            'label' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                    ],
                                ],
                            ],
                            'primaryDomain' => [
                                'type' => 'text',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'watchFile' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                            'name' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                    'language' => [
                        'type' => 'keyword',
                    ],
                    'isInteresting' => [
                        'type' => 'boolean',
                    ],
                    'insight' => [
                        'type' => 'text',
                    ],
                    'cfcRestricted' => [
                        'type' => 'boolean',
                    ],
                    'updatedAt' => [
                        'type' => 'date',
                    ],
                    'updatedBy' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                    'summary' => [
                        'type' => 'object',
                        'properties' => [
                            'fr' => [
                                'type' => 'text',
                                'analyzer' => 'french',
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                            ],
                        ],
                    ],
                    'summaryStatus' => [
                        'type' => 'keyword',
                    ],
                    'summaryGeneratedAt' => [
                        'type' => 'date',
                    ],
                    'aiValidation' => [
                        'type' => 'object',
                        'properties' => [
                            'status' => [
                                'type' => 'keyword',
                                'index' => true,
                            ],
                            'confidenceScore' => [
                                'type' => 'integer',
                                'index' => true,
                            ],
                            'validationReason' => [
                                'type' => 'object',
                                'properties' => [
                                    'fr' => [
                                        'type' => 'text',
                                        'analyzer' => 'french',
                                    ],
                                    'en' => [
                                        'type' => 'text',
                                        'analyzer' => 'english',
                                    ],
                                ],
                            ],
                            'processedAt' => [
                                'type' => 'date',
                                'index' => true,
                            ],
                            'referenceSubject' => [
                                'type' => 'text',
                                'analyzer' => 'french',
                            ],
                        ],
                    ],
                    'manualStatus' => [
                        'type' => 'keyword',
                        'index' => true,
                    ],
                    'validatedBy' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                    'validatedAt' => [
                        'type' => 'date',
                        'index' => true,
                    ],
                    'organisationId' => [
                        'type' => 'keyword',
                        'index' => true,
                    ],
                    'contentRatio' => [
                        'type' => 'float',
                        'index' => false,
                    ],
                    'canonicalUrl' => [
                        'type' => 'keyword',
                        'index' => true,
                    ],
                    'fingerprint' => [
                        'type' => 'object',
                        'properties' => [
                            'contentHash' => [
                                'type' => 'keyword',
                                'doc_values' => true,
                            ],
                            'simHash' => [
                                'type' => 'keyword',
                                'doc_values' => true,
                            ],
                            // 128-int signature stored for Jaccard recomputation;
                            // not indexed (queried via terms on lshBands instead).
                            'minHashSignature' => [
                                'type' => 'integer',
                                'index' => false,
                                'doc_values' => true,
                            ],
                            'lshBands' => [
                                'type' => 'keyword',
                                'doc_values' => true,
                            ],
                            // Stage 4 (title fallback): raw title shingles
                            // queried via `terms`. Caller re-verifies with
                            // an exact set-Jaccard on the candidates.
                            'titleShingles' => [
                                'type' => 'keyword',
                                'doc_values' => true,
                            ],
                        ],
                    ],
                    // `duplicates` is an audit/UX trace — never filtered or
                    // aggregated server-side, only read back via `_source` for
                    // display. We disable `doc_values` (and indexing on the
                    // long high-cardinality `url`) to avoid columnar storage
                    // costs on data that's only ever consumed as a JSON blob.
                    'duplicates' => [
                        'type' => 'nested',
                        'properties' => [
                            'url' => [
                                'type' => 'keyword',
                                'index' => false,
                                'doc_values' => false,
                            ],
                            'watchFileId' => [
                                'type' => 'keyword',
                                'doc_values' => false,
                            ],
                            'collectTaskId' => [
                                'type' => 'keyword',
                                'doc_values' => false,
                            ],
                            'sourceId' => [
                                'type' => 'keyword',
                                'doc_values' => false,
                            ],
                            'provider' => [
                                'type' => 'keyword',
                                'doc_values' => false,
                            ],
                            'collectedAt' => [
                                'type' => 'date',
                                'doc_values' => false,
                            ],
                            'outcome' => [
                                'type' => 'keyword',
                                'doc_values' => false,
                            ],
                            'matchStage' => [
                                'type' => 'keyword',
                                'doc_values' => false,
                            ],
                            'similarity' => [
                                'type' => 'float',
                                'index' => false,
                                'doc_values' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $version = new Version20260501000000();
        $version->up();
    }
}
