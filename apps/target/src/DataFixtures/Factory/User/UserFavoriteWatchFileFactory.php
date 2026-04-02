<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\User;

use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\WatchFile\WatchFile;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<UserFavoriteWatchFile>
 */
class UserFavoriteWatchFileFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return UserFavoriteWatchFile::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @return array{
     *     user: UserFactory,
     *     watchFile: WatchFileFactory,
     * }
     */
    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'watchFile' => WatchFileFactory::new(),
        ];
    }

    public function withUser(User $user): self
    {
        return $this->with([
            'user' => $user,
        ]);
    }

    public function withWatchFile(WatchFile $watchFile): self
    {
        return $this->with([
            'watchFile' => $watchFile,
        ]);
    }
}
