<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Actor;

use App\DataFixtures\Factory\Organisation\OrganisationFactory;
use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Actor>
 */
class ActorFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Actor::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     label: string,
     *     primaryDomain: ?string,
     *     organisation: OrganisationFactory,
     * }
     */
    protected function defaults(): array
    {
        return [
            'label' => self::faker()->unique()->company(),
            'primaryDomain' => self::faker()->unique()->domainName(),
            'organisation' => OrganisationFactory::new(),
        ];
    }

    public function withOrganisation(Organisation $organisation): self
    {
        return $this->with([
            'organisation' => $organisation,
        ]);
    }
}
