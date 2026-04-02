<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Organisation;

use App\Domain\Organisation\Organisation;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Organisation>
 */
class OrganisationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Organisation::class;
    }

    /**
     * @return array{
     *     name: string,
     *     keycloakId: string,
     * }
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->company(),
            'keycloakId' => self::faker()->uuid(),
        ];
    }
}
