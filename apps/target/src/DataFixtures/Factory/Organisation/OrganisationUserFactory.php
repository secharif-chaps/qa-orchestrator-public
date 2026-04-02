<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\Organisation;

use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\Organisation\MembershipType;
use App\Domain\Organisation\OrganisationRole;
use App\Domain\Organisation\OrganisationUser;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<OrganisationUser>
 */
class OrganisationUserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return OrganisationUser::class;
    }

    /**
     * @return array{
     *     organisation: OrganisationFactory,
     *     user: UserFactory,
     *     role: OrganisationRole,
     *     membershipType: MembershipType,
     * }
     */
    protected function defaults(): array
    {
        return [
            'organisation' => OrganisationFactory::new(),
            'user' => UserFactory::new(),
            'role' => OrganisationRole::MEMBER,
            'membershipType' => MembershipType::UNMANAGED,
        ];
    }
}
