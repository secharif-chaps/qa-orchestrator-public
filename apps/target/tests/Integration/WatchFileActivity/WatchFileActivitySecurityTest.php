<?php

declare(strict_types=1);

namespace App\Tests\Integration\WatchFileActivity;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\DataFixtures\Factory\WatchFileActivity\WatchFileActivityFactory;
use App\Tests\Integration\AbstractApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class WatchFileActivitySecurityTest extends AbstractApiTestCase
{
    public function testGetWatchFileActivitiesAsOwner(): void
    {
        $owner = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        WatchFileActivityFactory::new()->with([
            'watchFile' => $watchFile,
            'user' => $owner,
        ])->create();

        $client = $this->createAuthenticatedClient($owner);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/activities");

        $this->assertResponseIsSuccessful();
    }

    public function testGetWatchFileActivitiesDeniedForUnauthorizedUser(): void
    {
        $owner = UserFactory::new()->create();
        $unauthorizedUser = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($owner)
            ->withOwnedBy($owner)
            ->create();

        WatchFileActivityFactory::new()->with([
            'watchFile' => $watchFile,
            'user' => $owner,
        ])->create();

        $client = $this->createAuthenticatedClient($unauthorizedUser);
        $client->request('GET', "/api/watch_files/{$watchFile->getId()}/activities");

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testGetUserActivitiesAsConnectedUser(): void
    {
        $user = UserFactory::new()->create();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        WatchFileActivityFactory::new()->with([
            'watchFile' => $watchFile,
            'user' => $user,
        ])->create();

        $client = $this->createAuthenticatedClient($user);
        $client->request('GET', "/api/users/{$user->getId()}/activities");

        $this->assertResponseIsSuccessful();
    }

    public function testGetUserActivitiesDeniedForDifferentUser(): void
    {
        $targetUser = UserFactory::new()->create();
        $attackerUser = UserFactory::new()->create();

        $client = $this->createAuthenticatedClient($attackerUser);
        $client->request('GET', "/api/users/{$targetUser->getId()}/activities");

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }
}
