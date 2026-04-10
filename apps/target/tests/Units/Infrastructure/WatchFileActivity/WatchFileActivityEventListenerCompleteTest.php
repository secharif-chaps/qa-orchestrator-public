<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Actor\ActorStatusChangedEvent;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceStatusChangedEvent;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\Event\WatchFileCreatedEvent;
use App\Domain\WatchFile\Event\WatchFileSharedEvent;
use App\Domain\WatchFile\Event\WatchFileStatusChangedEvent;
use App\Domain\WatchFile\Event\WatchFileUnsharedEvent;
use App\Domain\WatchFile\Event\WatchFileUpdatedEvent;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileActivity\WatchFileActivity;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Domain\WatchFileActivity\WatchFileActivityGatewayInterface;
use App\Infrastructure\WatchFileActivity\WatchFileActivityEventListener;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class WatchFileActivityEventListenerCompleteTest extends TestCase
{
    use EntityUtilsTrait;
    use MockHelpersTrait;
    private WatchFileActivityEventListener $listener;
    private WatchFileActivityGatewayInterface&Stub $watchFileActivityGateway;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->watchFileActivityGateway = $this->createStub(WatchFileActivityGatewayInterface::class);
        $this->user = new User('test@example.com');
        $this->watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $this->user);
        $this->buildListener();
    }

    private function buildListener(): void
    {
        $this->listener = new WatchFileActivityEventListener(
            new WatchFileActivityLogger($this->createStub(TenantContext::class)),
            $this->watchFileActivityGateway,
        );
    }

    public function testOnWatchFileCreated(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $event = new WatchFileCreatedEvent($this->watchFile, $this->user);

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) {
                return WatchFileActivityActionType::CREATED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['watch_file_name'])
                    && $activity->getActionData()['watch_file_name'] === $this->watchFile->getName();
            }));

        $this->listener->onWatchFileCreated($event);
    }

    public function testOnWatchFileUpdated(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $changes = [
            'name' => [
                'old' => 'Old Name',
                'new' => 'New Name',
            ],
        ];
        $event = new WatchFileUpdatedEvent($this->watchFile, $this->user, $changes);

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($changes) {
                return WatchFileActivityActionType::UPDATED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['changes'])
                    && $activity->getActionData()['changes'] === $changes;
            }));

        $this->listener->onWatchFileUpdated($event);
    }

    public function testOnWatchFileUpdatedWithEmptyChanges(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $event = new WatchFileUpdatedEvent($this->watchFile, $this->user, []);

        $watchFileActivityGateway
            ->expects($this->never())
            ->method('save');

        $this->listener->onWatchFileUpdated($event);
    }

    public function testOnWatchFileStatusChanged(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $event = new WatchFileStatusChangedEvent(
            $this->watchFile,
            $this->user,
            WatchFileStatus::DRAFT,
            WatchFileStatus::ENABLED
        );

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) {
                return WatchFileActivityActionType::STATUS_CHANGED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['old_status'])
                    && 'draft' === $activity->getActionData()['old_status']
                    && isset($activity->getActionData()['new_status'])
                    && 'enabled' === $activity->getActionData()['new_status'];
            }));

        $this->listener->onWatchFileStatusChanged($event);
    }

    public function testOnSourceStatusChanged(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

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
            $this->watchFile
        );
        $this->forcePropertyValue($source, 'source_id');

        $event = new SourceStatusChangedEvent(
            $source,
            $this->watchFile,
            $this->user,
            SourceStatus::ACTIVE,
            SourceStatus::INACTIVE
        );

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($source) {
                return WatchFileActivityActionType::SOURCE_STATUS_CHANGED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['source_name'])
                    && $activity->getActionData()['source_name'] === $source->getName()
                    && isset($activity->getActionData()['source_id'])
                    && $activity->getActionData()['source_id'] === $source->getId()
                    && isset($activity->getActionData()['status'])
                    && 'active' === $activity->getActionData()['status'];
            }));

        $this->listener->onSourceStatusChanged($event);
    }

    public function testOnActorStatusChanged(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor_id');
        $event = new ActorStatusChangedEvent(
            $actor,
            $this->watchFile,
            $this->user,
            ActorStatus::ACTIVE,
            ActorStatus::INACTIVE
        );

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($actor) {
                return WatchFileActivityActionType::ACTOR_STATUS_CHANGED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['actor_name'])
                    && $activity->getActionData()['actor_name'] === $actor->getLabel()
                    && isset($activity->getActionData()['actor_id'])
                    && $activity->getActionData()['actor_id'] === $actor->getId()
                    && isset($activity->getActionData()['status'])
                    && 'active' === $activity->getActionData()['status'];
            }));

        $this->listener->onActorStatusChanged($event);
    }

    public function testOnWatchFileShared(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $sharedUser = new User('shared@example.com');
        $watchFileUser = new WatchFileUser($this->watchFile, $sharedUser, WatchFileUserRole::EDITOR);
        $event = new WatchFileSharedEvent($this->watchFile, $watchFileUser, $this->user);

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($sharedUser) {
                return WatchFileActivityActionType::SHARED_MODE_CHANGED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['user_id'])
                    && $activity->getActionData()['user_id'] === $sharedUser->getId()
                    && 'no_access' === $activity->getActionData()['old_value']
                    && 'editor' === $activity->getActionData()['new_value'];
            }));

        $this->listener->onWatchFileShared($event);
    }

    public function testOnWatchFileUnshared(): void
    {
        $watchFileActivityGateway = $this->createMockWithExpectations(WatchFileActivityGatewayInterface::class);
        $this->watchFileActivityGateway = $watchFileActivityGateway;
        $this->buildListener();

        $unsharedUser = new User('unshared@example.com');
        $watchFileUser = new WatchFileUser($this->watchFile, $unsharedUser, WatchFileUserRole::EDITOR);
        $event = new WatchFileUnsharedEvent($this->watchFile, $watchFileUser, $this->user);

        $watchFileActivityGateway
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (WatchFileActivity $activity) use ($unsharedUser) {
                return WatchFileActivityActionType::SHARED_MODE_CHANGED === $activity->getActionType()
                    && $activity->getWatchFile() === $this->watchFile
                    && $activity->getUser() === $this->user
                    && isset($activity->getActionData()['user_id'])
                    && $activity->getActionData()['user_id'] === $unsharedUser->getId()
                    && 'editor' === $activity->getActionData()['old_value']
                    && 'no_access' === $activity->getActionData()['new_value'];
            }));

        $this->listener->onWatchFileUnshared($event);
    }

    /**
     * Test that all event listeners are properly configured.
     */
    public function testAllEventListenersAreConfigured(): void
    {
        $reflection = new \ReflectionClass(WatchFileActivityEventListener::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $expectedMethods = [
            'onWatchFileCreated',
            'onWatchFileUpdated',
            'onWatchFileStatusChanged',
            'onSourceStatusChanged',
            'onActorStatusChanged',
            'onWatchFileShared',
            'onWatchFileUnshared',
        ];

        $actualMethods = array_map(fn ($method) => $method->getName(), $methods);

        foreach ($expectedMethods as $expectedMethod) {
            $this->assertContains($expectedMethod, $actualMethods,
                "Method {$expectedMethod} should exist in WatchFileActivityEventListener");
        }
    }
}
