<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\WatchFile\WatchFileUserEditableEnricher;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;

class WatchFileUserEditableEnricherTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileUserGateway $watchFileUserGateway;
    private WatchFileUserEditableEnricher $enricher;

    protected function setUp(): void
    {
        $this->watchFileUserGateway = new NullWatchFileUserGateway();
        $this->enricher = new WatchFileUserEditableEnricher($this->watchFileUserGateway);
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
        $this->assertFalse($watchFile->getUserEditable());
    }

    public function testEnrichWithWatchFileAndUserInContextButNoEditAccess(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $otherUser = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($otherUser, 'user2');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertFalse($watchFile->getUserEditable());
    }

    public function testEnrichWithWatchFileAndUserHasOwnerRole(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $otherUser = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($otherUser, 'user2');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        // Add user as owner of the watchfile
        $this->watchFileUserGateway->addWatchFileUser($watchFile, $user, WatchFileUserRole::OWNER);

        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertTrue($watchFile->getUserEditable());
    }

    public function testEnrichWithWatchFileAndUserHasEditorRole(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $otherUser = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($otherUser, 'user2');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        // Add user as editor of the watchfile
        $this->watchFileUserGateway->addWatchFileUser($watchFile, $user, WatchFileUserRole::EDITOR);

        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertTrue($watchFile->getUserEditable());
    }

    public function testEnrichWithWatchFileAndUserHasViewerRole(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $otherUser = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($otherUser, 'user2');

        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        // Add user as viewer of the watchfile (no edit access)
        $this->watchFileUserGateway->addWatchFileUser($watchFile, $user, WatchFileUserRole::VIEWER);

        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrich($watchFile, $context);

        $this->assertSame($watchFile, $result);
        $this->assertFalse($watchFile->getUserEditable());
    }

    public function testEnrichCollectionWithNoUserInContext(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile1, 'watchfile1');
        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation('Test Org', 'test-org-id'), $user);
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        $watchFiles = [$watchFile1, $watchFile2];

        $result = $this->enricher->enrichCollection($watchFiles);

        $this->assertSame($watchFiles, $result);
        $this->assertFalse($watchFile1->getUserEditable());
        $this->assertFalse($watchFile2->getUserEditable());
    }

    public function testEnrichCollectionWithUserInContext(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $otherUser = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($otherUser, 'user2');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user); // User is creator
        $this->watchFileUserGateway->addWatchFileUser($watchFile1, $user, WatchFileUserRole::OWNER);
        $this->forcePropertyValue($watchFile1, 'watchfile1');
        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser); // User is not creator
        $this->forcePropertyValue($watchFile2, 'watchfile2');

        $watchFiles = [$watchFile1, $watchFile2];
        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrichCollection($watchFiles, $context);

        $this->assertSame($watchFiles, $result);
        $this->assertTrue($watchFile1->getUserEditable()); // Creator has edit access
        $this->assertFalse($watchFile2->getUserEditable()); // Not creator, no role
    }

    public function testEnrichCollectionWithMixedAccess(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $otherUser = new User('user2', 'user2@example.com', [], 'User 2');
        $this->forcePropertyValue($otherUser, 'user2');

        $watchFile1 = new WatchFile('WatchFile 1', 'Objective 1', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user); // User is creator
        $this->forcePropertyValue($watchFile1, 'watchfile1');
        $watchFile2 = new WatchFile('WatchFile 2', 'Objective 2', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser); // User has owner role
        $this->forcePropertyValue($watchFile2, 'watchfile2');
        $watchFile3 = new WatchFile('WatchFile 3', 'Objective 3', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser); // User has editor role
        $this->forcePropertyValue($watchFile3, 'watchfile3');
        $watchFile4 = new WatchFile('WatchFile 4', 'Objective 4', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser); // User has viewer role
        $this->forcePropertyValue($watchFile4, 'watchfile4');
        $watchFile5 = new WatchFile('WatchFile 5', 'Objective 5', new Organisation(
            'Test Org',
            'test-org-id'
        ), $otherUser); // User has no access
        $this->forcePropertyValue($watchFile5, 'watchfile5');

        // Add user roles
        $this->watchFileUserGateway->addWatchFileUser($watchFile1, $user, WatchFileUserRole::OWNER);
        $this->watchFileUserGateway->addWatchFileUser($watchFile2, $user, WatchFileUserRole::OWNER);
        $this->watchFileUserGateway->addWatchFileUser($watchFile3, $user, WatchFileUserRole::EDITOR);
        $this->watchFileUserGateway->addWatchFileUser($watchFile4, $user, WatchFileUserRole::VIEWER);

        $watchFiles = [$watchFile1, $watchFile2, $watchFile3, $watchFile4, $watchFile5];
        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrichCollection($watchFiles, $context);

        $this->assertSame($watchFiles, $result);
        $this->assertTrue($watchFile1->getUserEditable()); // Creator
        $this->assertTrue($watchFile2->getUserEditable()); // Owner role
        $this->assertTrue($watchFile3->getUserEditable()); // Editor role
        $this->assertFalse($watchFile4->getUserEditable()); // Viewer role (no edit access)
        $this->assertFalse($watchFile5->getUserEditable()); // No access
    }

    public function testEnrichCollectionWithEmptyWatchFiles(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $this->forcePropertyValue($user, 'user1');

        $watchFiles = [];
        $context = [
            'user' => $user,
        ];

        $result = $this->enricher->enrichCollection($watchFiles, $context);

        $this->assertSame($watchFiles, $result);
        $this->assertEmpty($result);
    }
}
