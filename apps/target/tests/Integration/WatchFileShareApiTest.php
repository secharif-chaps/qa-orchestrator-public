<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileUserFactory;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUserRole;

class WatchFileShareApiTest extends AbstractApiTestCase
{
    public function testDeleteWatchFileShare(): void
    {
        $owner = UserFactory::createOne();
        $sharedUser = UserFactory::createOne();

        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->with([
                'name' => 'Test Watch File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $watchFileUser = WatchFileUserFactory::new()
            ->with([
                'watchFile' => $watchFile,
                'user' => $sharedUser,
                'role' => WatchFileUserRole::EDITOR,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($owner);
        $client->request(
            'DELETE',
            \sprintf('/api/watch_files/%s/share/%s', $watchFile->getId(), $watchFileUser->getId())
        );
        $this->assertResponseStatusCodeSame(204);
    }
}
