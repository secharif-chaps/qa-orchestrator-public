<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFile;

use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<WatchFileUser>
 */
class WatchFileUserFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return WatchFileUser::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     watchFile: WatchFileFactory,
     *     role: WatchFileUserRole,
     *     user: UserFactory,
     * }
     */
    protected function defaults(): array
    {
        /** @var WatchFileUserRole $role */
        $role = self::faker()->randomElement(WatchFileUserRole::editableValues());

        return [
            'watchFile' => WatchFileFactory::new(),
            'role' => $role,
            'user' => UserFactory::new(),
        ];
    }
}
