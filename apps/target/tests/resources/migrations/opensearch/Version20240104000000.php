<?php

declare(strict_types=1);

namespace App\Tests\resources\migrations\opensearch;

// This class doesn't extend AbstractOpenSearchMigration
class Version20240104000000
{
    public function getDescription(): string
    {
        return 'Invalid migration - not extending AbstractOpenSearchMigration';
    }
}
