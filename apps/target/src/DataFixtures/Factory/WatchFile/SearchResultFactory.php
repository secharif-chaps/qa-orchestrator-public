<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\Domain\WatchFile\SearchResult;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SearchResult>
 */
class SearchResultFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SearchResult::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     title: string,
     *     description: string,
     *     url: string,
     *     content: string|null,
     * }
     */
    protected function defaults(): array
    {
        return [
            'title' => self::faker()->sentence(),
            'description' => self::faker()->paragraph(),
            'url' => self::faker()->url(),
            'content' => self::faker()->optional()->paragraph(),
        ];
    }
}
