<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile;

use App\Application\WatchFile\CheckWatchFileOwnerQuotaAction;
use App\Application\WatchFile\CheckWatchFileOwnerQuotaHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\UsageLimit\Exception\QuotaExceededException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\UsageLimitConfigInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(CheckWatchFileOwnerQuotaHandler::class)]
class CheckWatchFileOwnerQuotaHandlerTest extends TestCase
{
    private NullWatchFileGateway $watchFileGateway;
    private CheckWatchFileOwnerQuotaHandler $handler;
    private UsageLimitConfigInterface&MockObject $usageLimitConfig;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->usageLimitConfig = $this->createMock(UsageLimitConfigInterface::class);
        $this->handler = new CheckWatchFileOwnerQuotaHandler($this->watchFileGateway, $this->usageLimitConfig);
    }

    public function testSucceedsWhenQuotaNotReached(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId);
        $this->createWatchFiles($user, WatchFileStatus::ENABLED, 20);
        $this->configureQuotaLimit(25);

        ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
    }

    public function testDoesNotThrowWhenExactlyAtLimit(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId);
        $this->createWatchFiles($user, WatchFileStatus::ENABLED, 24);
        $this->configureQuotaLimit(25, true);

        ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
    }

    public function testThrowsWhenQuotaExceeded(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId);
        $this->createWatchFiles($user, WatchFileStatus::ENABLED, 25);
        $this->configureQuotaLimit(25);

        $this->expectException(QuotaExceededException::class);
        $this->expectExceptionMessage('quota.watchfile_max_owned_non_archived');
        ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
    }

    public function testSucceedsWhenQuotaUnlimited(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId);
        $this->createWatchFiles($user, WatchFileStatus::ENABLED, 100);
        $this->configureQuotaLimit(null);

        ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
    }

    public function testExcludesArchivedWatchFiles(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId);
        $this->createWatchFiles($user, WatchFileStatus::ENABLED, 20);
        $this->createWatchFiles($user, WatchFileStatus::ARCHIVED, 10);
        $this->configureQuotaLimit(25);

        ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
    }

    public function testOnlyCountsWatchFilesForSpecificUser(): void
    {
        $userId1 = '550e8400-e29b-41d4-a716-446655440000';
        $userId2 = '550e8400-e29b-41d4-a716-446655440001';
        $user1 = new User($userId1);
        $user2 = new User($userId2);

        $this->createWatchFiles($user1, WatchFileStatus::ENABLED, 25);
        $this->createWatchFiles($user2, WatchFileStatus::ENABLED, 10);
        $this->configureQuotaLimit(25);

        $this->expectException(QuotaExceededException::class);
        ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId1)));
    }

    public function testThrowsWithCorrectExceptionDetails(): void
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $user = new User($userId);
        $this->createWatchFiles($user, WatchFileStatus::ENABLED, 26);
        $this->configureQuotaLimit(25);

        try {
            ($this->handler)(new CheckWatchFileOwnerQuotaAction(Uuid::fromString($userId)));
            $this->fail('Expected QuotaExceededException was not thrown');
        } catch (QuotaExceededException $e) {
            $this->assertSame(25, $e->limit());
            $this->assertSame(26, $e->current());
        }
    }

    private function createWatchFiles(User $user, WatchFileStatus $status, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $watchFile = new WatchFile("WatchFile {$i}", 'Objective', new Organisation(
                'Test Org',
                'test-org-id'
            ), $user);
            $watchFile->setStatus($status);
            $watchFile->addWatchFileUser(new WatchFileUser($watchFile, $user, WatchFileUserRole::OWNER));
            $this->watchFileGateway->save($watchFile);
        }
    }

    private function configureQuotaLimit(?int $limit, bool $inclusive = false): void
    {
        $this->usageLimitConfig
            ->expects($this->once())
            ->method('watchFileMaxOwnedNonArchived')
            ->willReturn(QuotaLimit::fromNullableInt($limit, $inclusive));
    }
}
