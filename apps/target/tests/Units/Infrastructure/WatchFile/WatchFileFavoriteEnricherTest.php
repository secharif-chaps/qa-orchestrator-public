<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\User\UserFavoriteWatchFile;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\WatchFileFavoriteEnricher;
use App\Tests\Units\Infrastructure\User\NullUserFavoriteWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;

class WatchFileFavoriteEnricherTest extends TestCase
{
    use EntityUtilsTrait;
    private NullUserFavoriteWatchFileGateway $userFavoriteWatchFileGateway;
    private WatchFileFavoriteEnricher $enricher;

    protected function setUp(): void
    {
        $this->userFavoriteWatchFileGateway = new NullUserFavoriteWatchFileGateway();
        $this->enricher = new WatchFileFavoriteEnricher($this->userFavoriteWatchFileGateway);
    }

    public function testSupports(): void
    {
        $this->assertSame(WatchFile::class, $this->enricher->supports());
    }

    public function testEnrichWithWatchFileButNoUserInContext(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $result = $this->enricher->enrich($watchFile);

        $this->assertSame($watchFile, $result);
        $this->assertFalse($watchFile->getIsFavorite());
    }

    public function testEnrichWithWatchFileAndUserInContextButNotFavorite(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertFalse($watchFile->getIsFavorite());
    }

    public function testEnrichWithWatchFileAndUserInContextAndIsFavorite(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $userFavorite = new UserFavoriteWatchFile($user, $watchFile);
        $this->userFavoriteWatchFileGateway->save($userFavorite);

        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertTrue($watchFile->getIsFavorite());
    }

    public function testEnrichCollectionWithNoUserInContext(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);

        $watchFiles = [$watchFile1, $watchFile2];

        $result = $this->enricher->enrichCollection($watchFiles);

        $this->assertSame($watchFiles, $result);
    }

    public function testEnrichCollectionWithUserInContext(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');
        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        $userFavorite1 = new UserFavoriteWatchFile($user, $watchFile1);
        $this->userFavoriteWatchFileGateway->save($userFavorite1);

        $watchFiles = [$watchFile1, $watchFile2];
        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrichCollection($watchFiles, $context);

        $this->assertSame($watchFiles, $result);
        $this->assertTrue($watchFile1->getIsFavorite());
        $this->assertFalse($watchFile2->getIsFavorite());
    }

    public function testEnrichCollectionWithMultipleFavorites(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');
        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');
        $watchFile3 = new WatchFile('WatchFile 3', 'Objective 3', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile3, 'watchfile3');

        $userFavorite1 = new UserFavoriteWatchFile($user, $watchFile1);
        $userFavorite3 = new UserFavoriteWatchFile($user, $watchFile3);
        $this->userFavoriteWatchFileGateway->save($userFavorite1);
        $this->userFavoriteWatchFileGateway->save($userFavorite3);

        $watchFiles = [$watchFile1, $watchFile2, $watchFile3];
        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrichCollection($watchFiles, $context);

        $this->assertSame($watchFiles, $result);
        $this->assertTrue($watchFile1->getIsFavorite());
        $this->assertFalse($watchFile2->getIsFavorite());
        $this->assertTrue($watchFile3->getIsFavorite());
    }
}
