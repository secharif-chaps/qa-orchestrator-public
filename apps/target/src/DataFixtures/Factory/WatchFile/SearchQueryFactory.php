<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\Domain\WatchFile\SearchQuery;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SearchQuery>
 */
class SearchQueryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SearchQuery::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     searchTerm: string,
     *     country: string,
     *     language: string,
     *     queryType: string,
     *     rationale: string,
     * }
     */
    protected function defaults(): array
    {
        return [
            'searchTerm' => self::faker()->sentence(),
            'country' => self::faker()->countryCode(),
            'language' => self::faker()->languageCode(),
            'queryType' => 'general',
            'rationale' => self::faker()->paragraph(),
        ];
    }
}
