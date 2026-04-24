<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

/**
 * Add contentRatio field to document index.
 * Computed at collection time as strlen(text_content) / strlen(raw_html).
 * Used by ContentRatioProcessor for quality scoring (TAR-1136).
 */
class Version20260403000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Add contentRatio field to document index for quality scoring';
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
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $version = new Version20260330000000();
        $version->up();
    }
}
