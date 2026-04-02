<?php

declare(strict_types=1);

namespace App\DataFixtures\Factory\WatchFileActivity;

use App\DataFixtures\Factory\Organisation\OrganisationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<WatchFileActivity>
 */
class WatchFileActivityFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return WatchFileActivity::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            'watchFile' => WatchFileFactory::new(),
            'user' => UserFactory::new(),
            'actionType' => WatchFileActivityActionType::CREATED,
            'actionData' => [],
            'organisation' => OrganisationFactory::new(),
        ];
    }
}
