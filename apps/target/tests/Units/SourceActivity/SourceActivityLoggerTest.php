<?php

declare(strict_types=1);

namespace App\Tests\Units\SourceActivity;

use App\Domain\Collect\CollectTaskStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\SourceActivity\SourceActivityLogger;
use PHPUnit\Framework\TestCase;

class SourceActivityLoggerTest extends TestCase
{
    private SourceActivityLogger $logger;
    private Source $source;
    private User $user;
    private WatchFile $watchFile;

    protected function setUp(): void
    {
        $this->logger = new SourceActivityLogger();

        $this->user = new User(
            id: 'user-123',
            email: 'test@example.com',
            roles: ['ROLE_USER'],
            userName: 'testuser'
        );

        $this->watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Test objective', organisation: new Organisation('Test Org', 'test-org-id'),
            createdBy: $this->user
        );

        $this->source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinent FR', 'Relevant EN'),
            actor: null,
            watchFile: $this->watchFile
        );

        $reflection = new \ReflectionClass($this->source);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($this->source, 'test-source-id-123');
    }

    public function testLogSourceConnected(): void
    {
        $connectionData = [
            'ip' => '127.0.0.1',
            'port' => 443,
        ];

        $event = $this->logger->logSourceConnected($this->source, $this->user, $connectionData);

        $this->assertInstanceOf(SourceActivity::class, $event);
        $this->assertEquals($this->source, $event->getSource());
        $this->assertEquals($this->user, $event->getUser());
        $this->assertEquals(SourceActivityActionType::SOURCE_CONNECTED, $event->getActionType());
        $this->assertArrayHasKey('source_name', $event->getActionData());
        $this->assertArrayHasKey('source_type', $event->getActionData());
        $this->assertArrayHasKey('source_url', $event->getActionData());
        $this->assertArrayHasKey('connection_data', $event->getActionData());
        $this->assertEquals($connectionData, $event->getActionData()['connection_data']);
        $this->assertArrayNotHasKey('provider_name', $event->getActionData());
    }

    public function testLogSourceConnectedWithProviderName(): void
    {
        $event = $this->logger->logSourceConnected($this->source, $this->user, [], 'bakus');

        $this->assertEquals(SourceActivityActionType::SOURCE_CONNECTED, $event->getActionType());
        $this->assertEquals('bakus', $event->getActionData()['provider_name']);
    }

    public function testLogSourceDisconnected(): void
    {
        $disconnectData = [
            'reason' => 'timeout',
        ];

        $event = $this->logger->logSourceDisconnected($this->source, $this->user, $disconnectData);

        $this->assertEquals(SourceActivityActionType::SOURCE_DISCONNECTED, $event->getActionType());
        $this->assertEquals($disconnectData, $event->getActionData()['disconnect_data']);
        $this->assertArrayNotHasKey('provider_name', $event->getActionData());
    }

    public function testLogSourceDisconnectedWithProviderName(): void
    {
        $event = $this->logger->logSourceDisconnected($this->source, $this->user, [], 'bakus');

        $this->assertEquals(SourceActivityActionType::SOURCE_DISCONNECTED, $event->getActionType());
        $this->assertEquals('bakus', $event->getActionData()['provider_name']);
    }

    public function testLogSourceError(): void
    {
        $error = new \RuntimeException('Connection failed');
        $context = [
            'attempt' => 3,
            'max_attempts' => 5,
        ];

        $event = $this->logger->logSourceError($this->source, $this->user, $error, $context);

        $this->assertEquals(SourceActivityActionType::SOURCE_ERROR, $event->getActionType());
        $this->assertEquals('Connection failed', $event->getActionData()['error_message']);
        $this->assertEquals(0, $event->getActionData()['error_code']);
        $this->assertEquals($context, $event->getActionData()['context']);
        $this->assertArrayNotHasKey('provider_name', $event->getActionData());
    }

    public function testLogSourceErrorWithProviderName(): void
    {
        $error = new \RuntimeException('Provider timeout');

        $event = $this->logger->logSourceError($this->source, $this->user, $error, null, 'bakus');

        $this->assertEquals(SourceActivityActionType::SOURCE_ERROR, $event->getActionType());
        $this->assertEquals('bakus', $event->getActionData()['provider_name']);
    }

    public function testLogSourceRecovered(): void
    {
        $recoveryData = [
            'downtime' => 300,
            'recovery_time' => '2024-01-01T12:05:00Z',
        ];

        $event = $this->logger->logSourceRecovered($this->source, $this->user, $recoveryData);

        $this->assertEquals(SourceActivityActionType::SOURCE_RECOVERED, $event->getActionType());
        $this->assertEquals($recoveryData, $event->getActionData()['recovery_data']);
        $this->assertArrayNotHasKey('provider_name', $event->getActionData());
    }

    public function testLogSourceRecoveredWithProviderName(): void
    {
        $event = $this->logger->logSourceRecovered($this->source, $this->user, [], 'bakus');

        $this->assertEquals(SourceActivityActionType::SOURCE_RECOVERED, $event->getActionType());
        $this->assertEquals('bakus', $event->getActionData()['provider_name']);
    }

    public function testLogSourceDataRetrieved(): void
    {
        $dataCount = 150;
        $dataInfo = [
            'format' => 'json',
            'size_kb' => 25,
        ];

        $event = $this->logger->logSourceDataRetrieved($this->source, $this->user, $dataCount, $dataInfo);

        $this->assertEquals(SourceActivityActionType::SOURCE_DATA_RETRIEVED, $event->getActionType());
        $this->assertEquals($dataCount, $event->getActionData()['data_count']);
        $this->assertEquals($dataInfo, $event->getActionData()['data_info']);
        $this->assertArrayNotHasKey('provider_name', $event->getActionData());
    }

    public function testLogSourceDataRetrievedWithProviderName(): void
    {
        $event = $this->logger->logSourceDataRetrieved($this->source, $this->user, 42, null, 'apify');

        $this->assertEquals(SourceActivityActionType::SOURCE_DATA_RETRIEVED, $event->getActionType());
        $this->assertEquals('apify', $event->getActionData()['provider_name']);
        $this->assertEquals(42, $event->getActionData()['data_count']);
    }

    public function testLogSourceConfigUpdated(): void
    {
        $changes = [
            'query' => [
                'old' => 'old query',
                'new' => 'new query',
            ],
        ];
        $oldConfig = [
            'query' => 'old query',
            'timeout' => 30,
        ];

        $event = $this->logger->logSourceConfigUpdated($this->source, $this->user, $changes, $oldConfig);

        $this->assertEquals(SourceActivityActionType::SOURCE_CONFIG_UPDATED, $event->getActionType());
        $this->assertEquals($changes, $event->getActionData()['changes']);
        $this->assertEquals($oldConfig, $event->getActionData()['old_config']);
    }

    public function testLogSourceStatusChanged(): void
    {
        $oldStatus = SourceStatus::INACTIVE;
        $newStatus = SourceStatus::ACTIVE;
        $context = [
            'manual' => true,
            'reason' => 'user_action',
        ];

        $event = $this->logger->logSourceStatusChanged($this->source, $this->user, $oldStatus, $newStatus, $context);

        $this->assertEquals(SourceActivityActionType::SOURCE_STATUS_CHANGED, $event->getActionType());
        $this->assertEquals('inactive', $event->getActionData()['old_status']);
        $this->assertEquals('active', $event->getActionData()['new_status']);
        $this->assertEquals($context, $event->getActionData()['context']);
    }

    public function testLogSourceAddedToWatchFile(): void
    {
        $context = [
            'via' => 'api',
            'batch' => false,
        ];

        $event = $this->logger->logSourceAddedToWatchFile($this->source, $this->user, $context);

        $this->assertEquals(SourceActivityActionType::SOURCE_ADDED_TO_WATCHFILE, $event->getActionType());
        $this->assertEquals('Test WatchFile', $event->getActionData()['watch_file_name']);
        $this->assertEquals($context, $event->getActionData()['context']);
    }

    public function testLogSourceCollectStatusChanged(): void
    {
        $oldStatus = CollectTaskStatus::QUEUED;
        $newStatus = CollectTaskStatus::RUNNING;
        $context = [
            'trigger' => 'automatic',
            'task_id' => 'task-123',
        ];

        $event = $this->logger->logSourceCollectStatusChanged(
            $this->source,
            $this->user,
            $oldStatus,
            $newStatus,
            $context
        );

        $this->assertInstanceOf(SourceActivity::class, $event);
        $this->assertEquals($this->source, $event->getSource());
        $this->assertEquals($this->user, $event->getUser());
        $this->assertEquals(SourceActivityActionType::SOURCE_COLLECTOR_STATUS_CHANGED, $event->getActionType());
        $this->assertEquals('queued', $event->getActionData()['old_collect_status']);
        $this->assertEquals('running', $event->getActionData()['new_collect_status']);
        $this->assertEquals($context, $event->getActionData()['context']);
        $this->assertArrayHasKey('source_id', $event->getActionData());
        $this->assertArrayHasKey('source_name', $event->getActionData());
        $this->assertArrayHasKey('source_type', $event->getActionData());
        $this->assertArrayHasKey('source_url', $event->getActionData());
        $this->assertArrayHasKey('timestamp', $event->getActionData());
        $this->assertArrayNotHasKey('provider_name', $event->getActionData());
    }

    public function testLogSourceCollectStatusChangedWithProviderName(): void
    {
        $oldStatus = CollectTaskStatus::CREATED;
        $newStatus = CollectTaskStatus::RUNNING;

        $event = $this->logger->logSourceCollectStatusChanged(
            $this->source,
            null,
            $oldStatus,
            $newStatus,
            [
                'provider_name' => 'bakus',
                'collect_task_id' => 'task-456',
            ]
        );

        $this->assertEquals(SourceActivityActionType::SOURCE_COLLECTOR_STATUS_CHANGED, $event->getActionType());
        $this->assertEquals('bakus', $event->getActionData()['provider_name']);
        $this->assertEquals('created', $event->getActionData()['old_collect_status']);
        $this->assertEquals('running', $event->getActionData()['new_collect_status']);
    }
}
