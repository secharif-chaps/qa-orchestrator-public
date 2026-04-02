<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\User;

use App\Domain\User\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
class UserFactory extends PersistentObjectFactory
{
    public const BASIL_USER_ID = '116f1d59-c72d-486c-bf55-a5e587d88cfc';

    public static function class(): string
    {
        return User::class;
    }

    public function defaultBasilUser(): self
    {
        return $this->with([
            'id' => self::BASIL_USER_ID,
            'roles' => [User::ROLE_USER],
            'firstName' => 'Basil',
            'lastName' => 'Target',
            'userName' => 'basil',
            'email' => 'basil@chapsvision.com',
        ]);
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     id: string,
     *     roles: list<string>,
     *     firstName: string,
     *     lastName: string,
     *     userName: string,
     *     email: string,
     * }
     */
    protected function defaults(): array
    {
        return [
            'id' => self::faker()->uuid(),
            'roles' => [User::ROLE_USER],
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'userName' => self::faker()->text(),
            'email' => self::faker()->email(),
        ];
    }
}
