<?php

declare(strict_types=1);

namespace App\Tests\resources\migrations\opensearch;

use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

class Version20240101000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Create initial users index';
    }

    public function up(): void
    {
        $this->createIndex('users', [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
            ],
            'mappings' => [
                'properties' => [
                    'name' => [
                        'type' => 'text',
                    ],
                    'email' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $this->deleteIndex('users');
    }
}
