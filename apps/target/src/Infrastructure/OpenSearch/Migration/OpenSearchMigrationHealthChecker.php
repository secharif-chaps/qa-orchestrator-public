<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenSearch\Migration;

use OpenSearch\Client;

class OpenSearchMigrationHealthChecker
{
    public function __construct(
        private readonly Client $openSearchClient,
    ) {
    }

    /**
     * Ensures all given aliases exist, meaning migrations have been run.
     *
     * @param string[] $aliases
     */
    public function ensureAliasesExist(array $aliases): void
    {
        $missingAliases = [];

        foreach ($aliases as $alias) {
            try {
                $exists = $this->openSearchClient->indices()
->existsAlias([
    'name' => $alias,
]);
            } catch (\Exception) {
                $exists = false;
            }

            if (!$exists) {
                $missingAliases[] = $alias;
            }
        }

        if (!empty($missingAliases)) {
            throw new \RuntimeException(\sprintf(
                'OpenSearch migrations have not been run. Missing aliases: [%s]. Run migrations before loading fixtures.',
                implode(', ', $missingAliases)
            ));
        }
    }
}
