<?php

declare(strict_types=1);

namespace OpenSearchMigrations;

use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

class Version20251015102945 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Change an event to be associated with a watchfile';
    }

    public function up(): void
    {
        $this->createIndex(WatchFileEvent::INDEX_NAME, [
            'mappings' => [
                'properties' => [
                    'id' => [
                        'type' => 'keyword',
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
        $version = new Version20251007140000();
        $version->up();
    }
}
