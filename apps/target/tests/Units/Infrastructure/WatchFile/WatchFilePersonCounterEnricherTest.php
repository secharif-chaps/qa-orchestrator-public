<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\WatchFile\WatchFilePersonCounterEnricher;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;

class WatchFilePersonCounterEnricherTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileUserGateway $watchFileUserGateway;
    private WatchFilePersonCounterEnricher $enricher;

    protected function setUp(): void
    {
        $this->watchFileUserGateway = new NullWatchFileUserGateway();
        $this->enricher = new WatchFilePersonCounterEnricher($this->watchFileUserGateway);
    }

    public function testSupports(): void
    {
        $this->assertSame(WatchFile::class, $this->enricher->supports());
    }

    public function testEnrichSetsPersonCountOnSingleWatchFile(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $watchFileUser = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser, 'user1');

        $this->watchFileUserGateway->watchFileUsers = [
            'user1' => $watchFileUser,
        ];

        $result = $this->enricher->enrich($watchFile);

        $this->assertSame($watchFile, $result);
        $this->assertSame(1, $watchFile->getWatchFileUsersCount());
    }

    public function testEnrichSetsZeroCountWhenNoUsers(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $result = $this->enricher->enrich($watchFile);

        $this->assertSame($watchFile, $result);
        $this->assertSame(0, $watchFile->getWatchFileUsersCount());
    }

    public function testEnrichCollectionSetsCountsOnMultipleWatchFiles(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');

        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        $watchFile3 = new WatchFile('WatchFile 3', 'Objective 3', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile3, 'watchfile3');

        $user2 = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($user2, 'user2');

        $watchFileUser1 = new WatchFileUser($watchFile1, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser1, 'wfu1');
        $watchFileUser2 = new WatchFileUser($watchFile1, $user2, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser2, 'wfu2');
        $watchFileUser3 = new WatchFileUser($watchFile2, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser3, 'wfu3');

        $this->watchFileUserGateway->watchFileUsers = [
            'wfu1' => $watchFileUser1,
            'wfu2' => $watchFileUser2,
            'wfu3' => $watchFileUser3,
        ];

        $watchFiles = [$watchFile1, $watchFile2, $watchFile3];

        $result = $this->enricher->enrichCollection($watchFiles);

        $this->assertSame($watchFiles, $result);
        $this->assertSame(2, $watchFile1->getWatchFileUsersCount());
        $this->assertSame(1, $watchFile2->getWatchFileUsersCount());
        $this->assertSame(0, $watchFile3->getWatchFileUsersCount());
    }

    public function testEnrichWithPersonCounterMethodDirectly(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');

        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        $watchFileUser1 = new WatchFileUser($watchFile1, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser1, 'wfu1');

        $this->watchFileUserGateway->watchFileUsers = [
            'wfu1' => $watchFileUser1,
        ];

        $watchFiles = [$watchFile1, $watchFile2];

        $result = $this->enricher->enrichWithPersonCounter($watchFiles);

        $this->assertSame($watchFiles, $result);
        $this->assertSame(1, $watchFile1->getWatchFileUsersCount());
        $this->assertSame(0, $watchFile2->getWatchFileUsersCount());
    }

    public function testEnrichCollectionWithEmptyArray(): void
    {
        $result = $this->enricher->enrichCollection([]);

        $this->assertSame([], $result);
    }

    public function testEnrichWithContext(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $context = [
            'some' => 'context',
        ];

        $watchFileUser1 = new WatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);
        $this->forcePropertyValue($watchFileUser1, 'wfu1');

        $this->watchFileUserGateway->watchFileUsers = [
            'wfu1' => $watchFileUser1,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertSame(1, $watchFile->getWatchFileUsersCount());
    }
}
