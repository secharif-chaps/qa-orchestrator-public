<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileActivity;

use App\Domain\Actor\Actor;
use App\Domain\Actor\ActorStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\MonitoringType;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileStatus;
use App\Domain\WatchFile\WatchFileUser;
use App\Domain\WatchFile\WatchFileUserRole;
use App\Domain\WatchFileActivity\WatchFileActivityActionType;
use App\Infrastructure\WatchFileActivity\WatchFileActivityLogger;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;

class WatchFileActivityLoggerCompleteTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileActivityLogger $logger;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->logger = new WatchFileActivityLogger($this->createStub(TenantContext::class));
        $this->user = new User('test@example.com');
        $this->watchFile = new WatchFile('Test WatchFile', 'Test Objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $this->user);
    }

    public function testLogCreate(): void
    {
        $activity = $this->logger->logCreate($this->watchFile, $this->user);

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::CREATED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('watch_file_name', $actionData);
        $this->assertEquals('Test WatchFile', $actionData['watch_file_name']);
    }

    public function testLogUpdate(): void
    {
        $changes = [
            'name' => [
                'old' => 'Old Name',
                'new' => 'New Name',
            ],
            'userObjective' => [
                'old' => 'Old Objective',
                'new' => 'New Objective',
            ],
        ];

        $activity = $this->logger->logUpdate($this->watchFile, $this->user, [
            'old' => [],
            'new' => $changes,
        ]);

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::UPDATED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('changes', $actionData);
        $this->assertEquals([
            'old' => [],
            'new' => $changes,
        ], $actionData['changes']);
    }

    public function testLogStatusChange(): void
    {
        $activity = $this->logger->logStatusChange(
            $this->watchFile,
            $this->user,
            WatchFileStatus::DRAFT,
            WatchFileStatus::ENABLED
        );

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::STATUS_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('old_status', $actionData);
        $this->assertEquals('draft', $actionData['old_status']);
        $this->assertArrayHasKey('new_status', $actionData);
        $this->assertEquals('enabled', $actionData['new_status']);
    }

    public function testLogSourceStatusChange(): void
    {
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

        $activity = $this->logger->logSourceStatusChange(
            $source,
            $this->watchFile,
            $this->user,
            SourceStatus::ACTIVE,
            SourceStatus::INACTIVE
        );

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::SOURCE_STATUS_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('source_name', $actionData);
        $this->assertEquals('Test Source', $actionData['source_name']);
        $this->assertArrayHasKey('source_id', $actionData);
        $this->assertEquals($source->getId(), $actionData['source_id']);
        $this->assertArrayHasKey('source_type', $actionData);
        $this->assertEquals('website', $actionData['source_type']);
        $this->assertArrayHasKey('source_url', $actionData);
        $this->assertEquals('https://example.com', $actionData['source_url']);
        $this->assertArrayHasKey('status', $actionData);
        $this->assertEquals('active', $actionData['status']);
        $this->assertArrayHasKey('old_status', $actionData);
        $this->assertEquals('inactive', $actionData['old_status']);
    }

    public function testLogActorStatusChange(): void
    {
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));

        $activity = $this->logger->logActorStatusChange(
            $actor,
            $this->watchFile,
            $this->user,
            ActorStatus::ACTIVE,
            ActorStatus::INACTIVE
        );

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::ACTOR_STATUS_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('actor_name', $actionData);
        $this->assertEquals('Test Actor', $actionData['actor_name']);
        $this->assertArrayHasKey('actor_id', $actionData);
        $this->assertEquals($actor->getId(), $actionData['actor_id']);
        $this->assertArrayHasKey('actor_primary_domain', $actionData);
        $this->assertEquals($actor->getPrimaryDomain(), $actionData['actor_primary_domain']);
        $this->assertArrayHasKey('status', $actionData);
        $this->assertEquals('active', $actionData['status']);
        $this->assertArrayHasKey('old_status', $actionData);
        $this->assertEquals('inactive', $actionData['old_status']);
    }

    public function testLogMonitoringTypeDetection(): void
    {
        $classificationData = [
            'confidence' => 85.5,
            'justification' => 'Based on content analysis',
            'secondary_types' => ['competitive', 'market'],
        ];

        $activity = $this->logger->logMonitoringTypeDetection(
            $this->watchFile,
            $this->user,
            MonitoringType::COMPETITIVE,
            $classificationData
        );

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::MONITORING_TYPE_DETECTED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('detected_monitoring_type', $actionData);
        $this->assertEquals('competitive', $actionData['detected_monitoring_type']);
        $this->assertArrayHasKey('confidence_score', $actionData);
        $this->assertEquals(85.5, $actionData['confidence_score']);
    }

    public function testLogShare(): void
    {
        $sharedUser = new User('shared@example.com');
        $this->forcePropertyValue($sharedUser, 'user_id');
        $watchFileUser = new WatchFileUser($this->watchFile, $sharedUser, WatchFileUserRole::EDITOR);

        $activity = $this->logger->logShare($this->watchFile, $watchFileUser, $this->user);

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::SHARED_MODE_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('user_id', $actionData);
        $this->assertEquals($sharedUser->getId(), $actionData['user_id']);
        $this->assertArrayHasKey('user_email', $actionData);
        $this->assertEquals($sharedUser->getEmail(), $actionData['user_email']);
        $this->assertArrayHasKey('old_value', $actionData);
        $this->assertEquals('no_access', $actionData['old_value']);
        $this->assertArrayHasKey('new_value', $actionData);
        $this->assertEquals('editor', $actionData['new_value']);
    }

    public function testLogUnshare(): void
    {
        $unsharedUser = new User('unshared@example.com');
        $this->forcePropertyValue($unsharedUser, 'user_id');
        $watchFileUser = new WatchFileUser($this->watchFile, $unsharedUser, WatchFileUserRole::EDITOR);

        $activity = $this->logger->logUnshare($this->watchFile, $watchFileUser, $this->user);

        $this->assertSame($this->watchFile, $activity->getWatchFile());
        $this->assertSame($this->user, $activity->getUser());
        $this->assertEquals(WatchFileActivityActionType::SHARED_MODE_CHANGED, $activity->getActionType());

        $actionData = $activity->getActionData();
        $this->assertArrayHasKey('user_id', $actionData);
        $this->assertEquals($unsharedUser->getId(), $actionData['user_id']);
        $this->assertArrayHasKey('user_email', $actionData);
        $this->assertEquals($unsharedUser->getEmail(), $actionData['user_email']);
        $this->assertArrayHasKey('old_value', $actionData);
        $this->assertEquals('editor', $actionData['old_value']);
        $this->assertArrayHasKey('new_value', $actionData);
        $this->assertEquals('no_access', $actionData['new_value']);
    }

    /**
     * Test that all required action types are covered.
     */
    public function testAllActionTypesAreCovered(): void
    {
        $expectedActionTypes = [
            'created',
            'updated',
            'source_status_changed',
            'status_changed',
            'actor_status_changed',
            'monitoring_type_detected',
            'reference_subject_updated',
            'shared_mode_changed',
        ];

        $actualActionTypes = array_map(fn ($case) => $case->value, WatchFileActivityActionType::cases());

        foreach ($expectedActionTypes as $expectedType) {
            $this->assertContains(
                $expectedType,
                $actualActionTypes,
                \sprintf(
                    'Action type %s should be defined in WatchFileActivityActionType enum. Available: %s',
                    $expectedType,
                    implode(', ', $actualActionTypes),
                ),
            );
        }
    }
}
