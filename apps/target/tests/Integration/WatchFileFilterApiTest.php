<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\WatchFile\WatchFileStatus;

class WatchFileFilterApiTest extends AbstractApiTestCase
{
    public function testIncludeArchivedFilterDefaultBehavior(): void
    {
        $user = UserFactory::createOne();

        // Create watch files with different statuses
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Enabled File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(2, $watchFiles); // Only draft and enabled, archived excluded by default

        $names = array_column($watchFiles, 'name');
        $this->assertContains('Draft File', $names);
        $this->assertContains('Enabled File', $names);
        $this->assertNotContains('Archived File', $names);
    }

    public function testIncludeArchivedFilterExplicitFalse(): void
    {
        $user = UserFactory::createOne();

        // Create watch files with different statuses
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?includeArchived=false');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(1, $watchFiles); // Only draft, archived excluded

        $this->assertSame('Draft File', $watchFiles[0]['name']);
    }

    public function testIncludeArchivedFilterTrue(): void
    {
        $user = UserFactory::createOne();

        // Create watch files with different statuses
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Enabled File',
                'status' => WatchFileStatus::ENABLED,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?includeArchived=true');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(3, $watchFiles); // All files included

        $names = array_column($watchFiles, 'name');
        $this->assertContains('Draft File', $names);
        $this->assertContains('Enabled File', $names);
        $this->assertContains('Archived File', $names);
    }

    public function testIncludeArchivedFilterWithStringValues(): void
    {
        $user = UserFactory::createOne();

        // Create watch files with different statuses
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Test with string "true"
        $response = $client->request('GET', '/api/watch_files?includeArchived=true');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        // Test with string "1"
        $response = $client->request('GET', '/api/watch_files?includeArchived=1');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        // Test with string "yes"
        $response = $client->request('GET', '/api/watch_files?includeArchived=yes');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        // Test with string "false"
        $response = $client->request('GET', '/api/watch_files?includeArchived=false');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);

        // Test with string "0"
        $response = $client->request('GET', '/api/watch_files?includeArchived=0');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);

        // Test with string "no"
        $response = $client->request('GET', '/api/watch_files?includeArchived=no');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
    }

    public function testIncludeArchivedFilterWithInvalidValues(): void
    {
        $user = UserFactory::createOne();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Test with invalid value - should default to false (exclude archived)
        $response = $client->request('GET', '/api/watch_files?includeArchived=invalid');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']); // Only draft file
        $this->assertSame('Draft File', $data['member'][0]['name']);
    }

    public function testOnlyFavoritesFilterFalse(): void
    {
        $user = UserFactory::createOne();

        // Create regular watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create favorite watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=false');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(2, $watchFiles); // Both files included

        $names = array_column($watchFiles, 'name');
        $this->assertContains('Regular File', $names);
        $this->assertContains('Favorite File', $names);
    }

    public function testOnlyFavoritesFilterTrue(): void
    {
        $user = UserFactory::createOne();

        // Create regular watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create favorite watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=true');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(1, $watchFiles); // Only favorite file

        $this->assertSame('Favorite File', $watchFiles[0]['name']);
        $this->assertTrue($watchFiles[0]['isFavorite']);
    }

    public function testOnlyFavoritesFilterDefaultBehavior(): void
    {
        $user = UserFactory::createOne();

        // Create regular watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create favorite watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(2, $watchFiles); // Both files included (default behavior)

        $names = array_column($watchFiles, 'name');
        $this->assertContains('Regular File', $names);
        $this->assertContains('Favorite File', $names);
    }

    public function testOnlyFavoritesFilterWithStringValues(): void
    {
        $user = UserFactory::createOne();

        // Create regular watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create favorite watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Test with string "true"
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=true');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);

        // Test with string "1"
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=1');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);

        // Test with string "yes"
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=yes');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);

        // Test with string "false"
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=false');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        // Test with string "0"
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=0');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        // Test with string "no"
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=no');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);
    }

    public function testOnlyFavoritesFilterWithInvalidValues(): void
    {
        $user = UserFactory::createOne();

        // Create regular watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create favorite watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Test with invalid value - should be ignored (show all files)
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=invalid');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']); // Both files included
    }

    public function testOnlyFavoritesFilterWithUnauthenticatedUser(): void
    {
        $user = UserFactory::createOne();

        // Create regular watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File',
            ])
            ->create();

        // Create favorite watch file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite File',
            ])
            ->create();

        $client = static::createClient();
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=true');

        $this->assertResponseStatusCodeSame(401); // Unauthenticated
    }

    public function testOnlyFavoritesFilterWithNoFavorites(): void
    {
        $user = UserFactory::createOne();

        // Create only regular watch files (no favorites)
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File 1',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular File 2',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=true');

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $watchFiles = $data['member'];
        $this->assertCount(0, $watchFiles); // No favorites, so empty result
    }

    public function testCombinedFiltersIncludeArchivedAndOnlyFavorites(): void
    {
        $user = UserFactory::createOne();

        // Create regular draft file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create favorite draft file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        // Create regular archived file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Regular Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        // Create favorite archived file
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Favorite Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Test: includeArchived=true, onlyFavorites=true
        $response = $client->request('GET', '/api/watch_files?includeArchived=true&onlyFavorites=true');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']); // Only favorite files (both draft and archived)

        $names = array_column($data['member'], 'name');
        $this->assertContains('Favorite Draft File', $names);
        $this->assertContains('Favorite Archived File', $names);

        // Test: includeArchived=false, onlyFavorites=true
        $response = $client->request('GET', '/api/watch_files?includeArchived=false&onlyFavorites=true');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']); // Only favorite draft file

        $this->assertSame('Favorite Draft File', $data['member'][0]['name']);

        // Test: includeArchived=true, onlyFavorites=false
        $response = $client->request('GET', '/api/watch_files?includeArchived=true&onlyFavorites=false');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(4, $data['member']); // All files

        // Test: includeArchived=false, onlyFavorites=false (default behavior)
        $response = $client->request('GET', '/api/watch_files?includeArchived=false&onlyFavorites=false');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']); // Only non-archived files

        $names = array_column($data['member'], 'name');
        $this->assertContains('Regular Draft File', $names);
        $this->assertContains('Favorite Draft File', $names);
    }

    public function testFiltersWithSorting(): void
    {
        $user = UserFactory::createOne();

        // Create files with different names and statuses
        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Charlie Draft File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->withUserFavorite($user)
            ->with([
                'name' => 'Alpha Favorite File',
                'status' => WatchFileStatus::DRAFT,
            ])
            ->create();

        WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->with([
                'name' => 'Beta Archived File',
                'status' => WatchFileStatus::ARCHIVED,
            ])
            ->create();

        $client = $this->createAuthenticatedClient($user);

        // Test with sorting by name and onlyFavorites=true
        $response = $client->request('GET', '/api/watch_files?onlyFavorites=true&sort[name]=asc');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertSame('Alpha Favorite File', $data['member'][0]['name']);

        // Test with sorting by name and includeArchived=true
        $response = $client->request('GET', '/api/watch_files?includeArchived=true&sort[name]=asc');
        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(3, $data['member']);

        $names = array_column($data['member'], 'name');
        $this->assertEquals(['Alpha Favorite File', 'Beta Archived File', 'Charlie Draft File'], $names);
    }
}
