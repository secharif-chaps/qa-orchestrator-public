<?php

declare(strict_types=1);

namespace App\Tests\Units\UserInterface\Command;

use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Application\WatchFile\Quota\CheckDocumentQuotaAction;
use App\Application\WatchFile\Quota\CheckDocumentQuotaHandler;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Infrastructure\UsageLimit\Config\UsageLimitConfig;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use App\UserInterface\Command\CheckDocumentQuotaCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CheckDocumentQuotaCommandTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private NullDocumentGateway $documentGateway;
    private NullWatchFileActivityGateway $activityGateway;
    private WatchFileActivityLogger $activityLogger;
    private NullMessageBus $messageBus;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->documentGateway = new NullDocumentGateway();
        $this->activityGateway = new NullWatchFileActivityGateway();
        $this->activityLogger = new WatchFileActivityLogger($this->createStub(TenantContext::class));
        $this->messageBus = new NullMessageBus();
    }

    public function testCommandProcessesExceedingWatchFiles(): void
    {
        $owner = $this->createOwner();

        $watchFile = new WatchFile('Quota WF', 'Objective', new Organisation('Test Org', 'test-org-id'), $owner);
        $this->forcePropertyValue($watchFile, 'wf-1');
        $watchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $watchFile->addWatchFileUser($watchFileUser);
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);
        $this->documentGateway->setExceedingCounts([
            'wf-1' => 15000,
        ]);

        $tester = $this->createCommandTester(10000);
        $status = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Processed 1 WatchFile', $tester->getDisplay());
    }

    public function testCommandHonoursCustomQuotaOption(): void
    {
        $owner = $this->createOwner();

        $watchFile = new WatchFile('Custom quota', 'Objective', new Organisation('Test Org', 'test-org-id'), $owner);
        $this->forcePropertyValue($watchFile, 'wf-2');
        $watchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $watchFile->addWatchFileUser($watchFileUser);
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);
        $this->documentGateway->setExceedingCounts([
            'wf-2' => 15000,
        ]);

        $tester = $this->createCommandTester(10000);
        $status = $tester->execute([
            '--quota' => 20000,
        ]);

        $this->assertSame(Command::SUCCESS, $status);
        $this->assertStringContainsString('Using custom quota: 20000', $tester->getDisplay());
        $this->assertStringContainsString('No WatchFiles exceed the document quota limit.', $tester->getDisplay());
    }

    public function testCommandFailsOnNegativeQuota(): void
    {
        $tester = $this->createCommandTester(10000);

        $status = $tester->execute([
            '--quota' => -5,
        ]);

        $this->assertSame(Command::FAILURE, $status);
        $this->assertStringContainsString('Quota must be a positive integer', $tester->getDisplay());
    }

    private function createCommandTester(?int $documentQuota): CommandTester
    {
        $handler = $this->createHandlerWithDocumentQuota($documentQuota);
        $handler->setWatchFileGateway($this->watchFileGateway);

        $this->messageBus->fakeHandler = function ($action) use ($handler) {
            if ($action instanceof CheckDocumentQuotaAction) {
                return $handler($action);
            }
            if ($action instanceof ChangeWatchFileStatusAction) {
                $watchFile = $this->watchFileGateway->get($action->watchFileId);
                $watchFile->setStatus($action->status);
                $this->watchFileGateway->save($watchFile);

                return $watchFile;
            }

            return null;
        };

        $command = new CheckDocumentQuotaCommand($this->messageBus);

        return new CommandTester($command);
    }

    private function createHandlerWithDocumentQuota(?int $documentQuota): CheckDocumentQuotaHandler
    {
        $config = new UsageLimitConfig([
            'watchfile' => [
                'max_owned_non_archived' => null,
                'max_active_per_user' => null,
            ],
            'source' => [
                'max_per_watchfile' => null,
                'max_active_per_watchfile' => null,
            ],
            'actor' => [
                'max_per_watchfile' => null,
            ],
            'document' => [
                'max_per_watchfile' => $documentQuota,
            ],
        ]);

        return new CheckDocumentQuotaHandler(
            $config,
            $this->activityLogger,
            $this->activityGateway,
            $this->documentGateway,
            $this->messageBus,
        );
    }

    private function createOwner(): User
    {
        return new User('owner-id', 'owner@basil.test');
    }
}
