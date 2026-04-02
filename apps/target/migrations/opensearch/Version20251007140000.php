<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

/**
 * Create watch_file_events index.
 */
final class Version20251007140000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Create watch_file_events index for storing extracted events';
    }

    public function up(): void
    {
        $this->createIndex(WatchFileEvent::INDEX_NAME, [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'analysis' => [
                    'analyzer' => [
                        'french' => [
                            'type' => 'standard',
                            'stopwords' => '_french_',
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
                    ],
                    'start_date' => [
                        'type' => 'date',
                    ],
                    'end_date' => [
                        'type' => 'date',
                    ],
                    'description' => [
                        'type' => 'object',
                        'properties' => [
                            'fr' => [
                                'type' => 'text',
                                'analyzer' => 'french',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 512,
                                    ],
                                ],
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 512,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'event_type' => [
                        'type' => 'keyword',
                    ],
                    'actors' => [
                        'type' => 'nested',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                            'name' => [
                                'type' => 'keyword',
                            ],
                            'role' => [
                                'type' => 'keyword',
                            ],
                            'watchfile_id' => [
                                'type' => 'keyword',
                            ],
                        ],
                    ],
                    'document_links' => [
                        'type' => 'nested',
                        'properties' => [
                            'id' => [
                                'type' => 'keyword',
                            ],
                            'text_extract' => [
                                'type' => 'text',
                            ],
                        ],
                    ],
                    'extraction_status' => [
                        'type' => 'keyword',
                    ],
                    'created_at' => [
                        'type' => 'date',
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $this->deleteIndex(WatchFileEvent::INDEX_NAME);
    }
}
