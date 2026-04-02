<?php

declare(strict_types=1);

namespace App\Tests\resources\migrations\opensearch;

use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

class Version20240102000000 extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Add age field to users index';
    }

    public function up(): void
    {
        $this->updateIndex('users', [
            'mappings' => [
                'properties' => [
                    'age' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]);
    }

    public function down(): void
    {
        $this->updateIndex('users', [
            'mappings' => [
                'properties' => [
                    'age' => [
                        'enabled' => false,
                    ],
                ],
            ],
        ]);
    }
}
