<?php

declare(strict_types=1);

namespace App\Tests\resources\migrations\opensearch;

use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

class Version20240103000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Create products index';
    }

    public function up(): void
    {
        $this->createIndex('products', [
            'settings' => [
                'number_of_shards' => 2,
                'number_of_replicas' => 1,
            ],
            'mappings' => [
                'properties' => [
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'standard',
                    ],
                    'price' => [
                        'type' => 'float',
                    ],
                    'category' => [
                        'type' => 'keyword',
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $this->deleteIndex('products');
    }
}
