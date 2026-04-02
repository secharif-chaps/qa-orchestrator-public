<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\Document\Document;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

/**
 * Create document index.
 */
final class Version20250905094955 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Create document index';
    }

    public function up(): void
    {
        // Create the document index with proper configuration
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
                    'status' => [
                        'type' => 'keyword',
                    ],
                    'validationReason' => [
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
                            ],
                            'primaryDomain' => [
                                'type' => 'text',
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
                        'type' => 'keyword',
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        // Delete the document index
        $this->deleteIndex(Document::INDEX_NAME);
    }
}
