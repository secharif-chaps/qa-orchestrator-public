<?php

declare(strict_types=1);

namespace App\DataFixtures;

trait OpenSearchFixtureTrait
{
    /**
     * Clear all documents from an OpenSearch index.
     */
    private function clearOpenSearchIndex(string $indexName): void
    {
        try {
            $indexExists = $this->openSearchClient->indices()
                ->exists([
                    'index' => $indexName,
                ]);

            if (!$indexExists) {
                echo \sprintf("Index '%s' does not exist. Skipping clear operation.\n", $indexName);

                return;
            }

            echo \sprintf("Clearing existing documents from index '%s'...\n", $indexName);

            $responseArray = $this->openSearchClient->deleteByQuery([
                'index' => $indexName,
                'body' => [
                    'query' => [
                        'match_all' => (object) [],
                    ],
                ],
                'conflicts' => 'proceed',
                'refresh' => true,
            ]);

            $deletedCount = $responseArray['deleted'] ?? 0;
            echo \sprintf("Successfully deleted %d documents from index '%s'.\n", $deletedCount, $indexName);
        } catch (\Exception $e) {
            echo \sprintf("Error clearing index '%s': %s\n", $indexName, $e->getMessage());
        }
    }
}
