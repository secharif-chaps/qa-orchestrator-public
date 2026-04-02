<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

/**
 * Add events_extracted flag to document index.
 */
final class Version20251013100000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Add events_extracted and events_extracted_at fields to document index';
    }

    public function up(): void
    {
        $this->updateIndex(Document::INDEX_NAME,
            [
                'mappings' => [
                    'properties' => [
                        'id' => [
                            'type' => 'keyword',
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
                        'events_extracted' => [
                            'type' => 'boolean',
                        ],
                        'events_extracted_at' => [
                            'type' => 'date',
                        ],
                    ],
                ],
            ]);
    }

    public function down(): void
    {
        $this->updateIndex(Document::INDEX_NAME, [
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
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
                ],
            ],
        ]);
    }
}
