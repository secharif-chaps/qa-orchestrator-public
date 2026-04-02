<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

/**
 * Add title field to watch_file_events index.
 */
final class Version20251224143634 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Add title field to watch_file_events index';
    }

    public function up(): void
    {
        $this->updateIndex(WatchFileEvent::INDEX_NAME, [
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
                    ],
                    'title' => [
                        'type' => 'object',
                        'properties' => [
                            'fr' => [
                                'type' => 'text',
                                'analyzer' => 'french',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256,
                                    ],
                                ],
                            ],
                            'en' => [
                                'type' => 'text',
                                'analyzer' => 'english',
                                'fields' => [
                                    'keyword' => [
                                        'type' => 'keyword',
                                        'ignore_above' => 256,
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'startDate' => [
                        'type' => 'date',
                    ],
                    'endDate' => [
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
                    'eventType' => [
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
                        ],
                    ],
                    'documentLinks' => [
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
                    'extractionStatus' => [
                        'type' => 'keyword',
                    ],
                    'createdAt' => [
                        'type' => 'date',
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
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $version = new Version20251015102945();
        $version->up();
    }
}
