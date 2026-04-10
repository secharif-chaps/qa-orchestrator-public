<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFileActivity\WatchFileActivityEventListener;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WatchFileActivityEventListenerTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileActivityEventListener $listener;
    private WatchFileActivityGatewayInterface&MockObject $watchFileActivityGateway;

    protected function setUp(): void
    {
        $this->watchFileActivityGateway = $this->createMock(WatchFileActivityGatewayInterface::class);
        $this->listener = new WatchFileActivityEventListener(
            new WatchFileActivityLogger($this->createStub(TenantContext::class)),
            $this->watchFileActivityGateway,
        );
    }

    public function testOnWatchFileCreated(): void
    {
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $user = new User('test@example.com');
        $event = new WatchFileCreatedEvent($watchFile, $user);

        $this->watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($watchFile, $user) {
                return WatchFileActivityActionType::CREATED === $activity->getActionType()
                    && $activity->getWatchFile() === $watchFile
                    && $activity->getUser() === $user
                    && isset($activity->getActionData()['watch_file_name'])
                    && $activity->getActionData()['watch_file_name'] === $watchFile->getName();
            }));

        $this->listener->onWatchFileCreated($event);
    }

    public function testOnWatchFileUpdated(): void
    {
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $user = new User('test@example.com');
        $changes = [
            'name' => [
                'old' => 'old',
                'new' => 'new',
            ],
        ];
        $event = new WatchFileUpdatedEvent($watchFile, $user, $changes);

        $this->watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($watchFile, $user, $changes) {
                return WatchFileActivityActionType::UPDATED === $activity->getActionType()
                    && $activity->getWatchFile() === $watchFile
                    && $activity->getUser() === $user
                    && isset($activity->getActionData()['changes'])
                    && $activity->getActionData()['changes'] === $changes;
            }));

        $this->listener->onWatchFileUpdated($event);
    }

    public function testOnWatchFileUpdatedWithEmptyChanges(): void
    {
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $user = new User('test@example.com');
        $event = new WatchFileUpdatedEvent($watchFile, $user, []);

        $this->watchFileActivityGateway
            ->expects($this->never())
            ->method('save');

        $this->listener->onWatchFileUpdated($event);
    }

    public function testOnSourceStatusChanged(): void
    {
        $watchFile = new WatchFile('test-watchfile', 'test-objective', new Organisation('Test Org', 'test-org-id'));
        $user = new User('test@example.com');
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            TranslatedText::fromArray([
                'fr' => 'Description FR',
                'en' => 'Description EN',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'Relevance FR',
                'en' => 'Relevance EN',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $event = new SourceStatusChangedEvent($source, $watchFile, $user, SourceStatus::ACTIVE, SourceStatus::INACTIVE);

        $this->watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($watchFile, $user, $source) {
                return WatchFileActivityActionType::SOURCE_STATUS_CHANGED === $activity->getActionType()
                    && $activity->getWatchFile() === $watchFile
                    && $activity->getUser() === $user
                    && isset($activity->getActionData()['source_name'])
                    && $activity->getActionData()['source_name'] === $source->getName()
                    && isset($activity->getActionData()['source_id'])
                    && $activity->getActionData()['source_id'] === $source->getId()
                    && isset($activity->getActionData()['status'])
                    && 'active' === $activity->getActionData()['status'];
            }));

        $this->listener->onSourceStatusChanged($event);
    }
}
