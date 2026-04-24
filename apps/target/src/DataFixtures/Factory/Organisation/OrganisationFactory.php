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
    public const DEFAULT_ORGANISATION_ID = '10000000-0000-0000-0000-000000000001';
    public const DEFAULT_ORGANISATION_KEYCLOAK_ID = 'bb23edbd-aaa0-4e26-863d-dfff9c700827';

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

    public function defaultOrganisation(): self
    {
        return $this->with([
            'id' => self::DEFAULT_ORGANISATION_ID,
            'keycloakId' => self::DEFAULT_ORGANISATION_KEYCLOAK_ID,
            'name' => 'ChapsMind Dev',
        ]);
    }
}
