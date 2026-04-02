<?php

declare(strict_types=1);

namespace App\Tests\resources\migrations\opensearch;

use App\Infrastructure\OpenSearch\Migration\AbstractOpenSearchMigration;

class InvalidFile extends AbstractOpenSearchMigration
{
    public function getDescription(): string
    {
        return 'Invalid migration file';
    }

    public function up(): void
    {
        // This should be filtered out because filename doesn't start with Version
    }

    public function down(): void
    {
        // This should be filtered out because filename doesn't start with Version
    }
}
