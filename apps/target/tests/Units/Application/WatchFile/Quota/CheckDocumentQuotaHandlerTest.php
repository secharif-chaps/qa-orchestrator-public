<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\WatchFile\Quota;

use App\Application\WatchFile\ChangeWatchFileStatusAction;
use App\Application\WatchFile\Quota\CheckDocumentQuotaAction;
use App\Application\WatchFile\Quota\CheckDocumentQuotaHandler;
use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\UsageLimit\Config\UsageLimitConfig;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use App\Tests\Units\Infrastructure\Document\NullDocumentGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Units\Infrastructure\WatchFileActivity\NullWatchFileActivityGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use PHPUnit\Framework\TestCase;

class CheckDocumentQuotaHandlerTest extends TestCase
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
        $this->activityLogger = new WatchFileActivityLogger($this->createStub(TenantContext::class));
        $this->activityGateway = new NullWatchFileActivityGateway();
        $this->messageBus = new NullMessageBus();
    }

    public function testWatchFilesOverQuotaAreMovedToDraftAndActivityLogged(): void
    {
        $owner = new User(UserFactory::BASIL_USER_ID, 'basil@chapsvision.com');

        $watchFile = new WatchFile('Quota WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'), $owner);
        $this->forcePropertyValue($watchFile, 'watchfile-quota');
        $watchFileUser = new WatchFileUser($watchFile, $owner, WatchFileUserRole::OWNER);
        $watchFile->addWatchFileUser($watchFileUser);
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);
        $this->documentGateway->setExceedingCounts([
            'watchfile-quota' => 15000,
        ]);

        $this->messageBus->fakeHandler = function ($action) {
            if ($action instanceof ChangeWatchFileStatusAction) {
                $watchFile = $this->watchFileGateway->get($action->watchFileId);
                $watchFile->setStatus($action->status);
                $this->watchFileGateway->save($watchFile);

                return $watchFile;
            }

            return null;
        };

        $handler = $this->createHandlerWithDocumentQuota(10000);

        $processed = $handler(new CheckDocumentQuotaAction());

        $this->assertSame(1, $processed);
        $this->assertSame(WatchFileStatus::DRAFT, $watchFile->getStatus());
        $activities = $this->activityGateway->getAllSaved();
        $this->assertCount(1, $activities);
        $activity = $activities[0];
        $this->assertSame(WatchFileActivityActionType::DOCUMENT_QUOTA_EXCEEDED, $activity->getActionType());
        $this->assertSame(15000, $activity->getActionData()['document_count']);
        $this->assertSame(10000, $activity->getActionData()['quota']);
    }

    public function testHandlerReturnsZeroWhenNoWatchFilesExceedQuota(): void
    {
        $this->documentGateway->setExceedingCounts([]);

        $handler = $this->createHandlerWithDocumentQuota(10000);
        $processed = $handler(new CheckDocumentQuotaAction());

        $this->assertSame(0, $processed);
        $this->assertCount(0, $this->activityGateway->getAllSaved());
    }

    public function testSkipsWatchFileWhenOwnerMissing(): void
    {
        $this->documentGateway->setExceedingCounts([
            'watchfile-quota' => 20000,
        ]);

        $watchFile = new WatchFile('Missing owner case', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watchfile-quota');
        $watchFile->setStatus(WatchFileStatus::ENABLED);
        $this->watchFileGateway->save($watchFile);

        $handler = $this->createHandlerWithDocumentQuota(10000);
        $processed = $handler(new CheckDocumentQuotaAction());

        $this->assertSame(0, $processed);
        $this->assertCount(0, $this->activityGateway->getAllSaved());
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

        $handler = new CheckDocumentQuotaHandler(
            $config,
            $this->activityLogger,
            $this->activityGateway,
            $this->documentGateway,
            $this->messageBus,
        );
        $handler->setWatchFileGateway($this->watchFileGateway);

        return $handler;
    }
}
