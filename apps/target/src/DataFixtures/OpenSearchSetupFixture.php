<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Document\Document;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\OpenSearch\Migration\OpenSearchMigrationHealthChecker;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use OpenSearch\Client;

/**
 * Runs once before all OpenSearch fixtures: verifies migrations are applied,
 * then purges all managed aliases so each fixture starts from a clean state.
 */
class OpenSearchSetupFixture extends Fixture
{
    private const array MANAGED_ALIASES = [Document::INDEX_NAME, WatchFileEvent::INDEX_NAME];

    public function __construct(
        private readonly OpenSearchMigrationHealthChecker $healthChecker,
        private readonly Client $openSearchClient,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->healthChecker->ensureAliasesExist(self::MANAGED_ALIASES);

        foreach (self::MANAGED_ALIASES as $alias) {
            $this->purgeAlias($alias);
        }
    }

    private function purgeAlias(string $alias): void
    {
        echo \sprintf("Purging alias '%s'...\n", $alias);

        $response = $this->openSearchClient->deleteByQuery([
            'index' => $alias,
            'body' => [
                'query' => [
                    'match_all' => (object) [],
                ],
            ],
            'conflicts' => 'proceed',
            'refresh' => true,
        ]);

        $deleted = $response['deleted'] ?? 0;
        echo \sprintf("Deleted %d documents from alias '%s'.\n", $deleted, $alias);
    }
}
