<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Collect\Stream;

use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Collect\Stream\WebSocketStreamStatistics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(WebSocketStreamStatistics::class)]
final class WebSocketStreamStatisticsTest extends TestCase
{
    private WebSocketStreamStatistics $statistics;

    protected function setUp(): void
    {
        parent::setUp();
        $this->statistics = new WebSocketStreamStatistics();
    }

    #[Test]
    public function itInitializesWithZeroValues(): void
    {
        self::assertSame(
            [
                'ping_count' => 0,
                'pong_count' => 0,
                'messages_received' => 0,
                'documents_received' => 0,
                'pending_messages' => 0,
                'active_connections' => 0,
                'total_connections_handled' => 0,
                'uptime' => '00:00:00',
                'memory_usage' => $this->statistics->getMemoryUsage(),
                'last_message_at' => null,
            ],
            $this->statistics->toArray(),
        );
    }

    #[Test]
    public function itIncrementsPingCount(): void
    {
        $this->statistics->incrementPing();
        $this->statistics->incrementPing();

        $array = $this->statistics->toArray();
        self::assertSame(2, $array['ping_count']);
    }

    #[Test]
    public function itIncrementsPongCount(): void
    {
        $this->statistics->incrementPong();
        $this->statistics->incrementPong();
        $this->statistics->incrementPong();

        $array = $this->statistics->toArray();
        self::assertSame(3, $array['pong_count']);
    }

    #[Test]
    public function itIncrementsMessagesReceivedAndUpdatesLastMessageTime(): void
    {
        $this->statistics->incrementMessagesReceived();

        $array = $this->statistics->toArray();
        self::assertSame(1, $array['messages_received']);
        self::assertNotNull($array['last_message_at']);
    }

    #[Test]
    public function itIncrementsDocumentsReceived(): void
    {
        $this->statistics->incrementDocumentsReceived();
        $this->statistics->incrementDocumentsReceived();

        $array = $this->statistics->toArray();
        self::assertSame(2, $array['documents_received']);
    }

    #[Test]
    public function itIncrementsMessagesProcessedWithDocument(): void
    {
        $event = new CollectDataReceivedEvent(
            'task-123',
            'provider-456',
            [
                'type' => 'merged_result',
                'content' => 'test',
            ]
        );

        $this->statistics->incrementMessagesProcessed($event);

        $array = $this->statistics->toArray();
        self::assertSame(1, $array['documents_received']);
    }

    #[Test]
    public function itIncrementsMessagesProcessedWithoutDocument(): void
    {
        $event = new CollectDataReceivedEvent(
            'task-123',
            'provider-456',
            [
                'type' => 'status',
                'status' => 'ok',
            ]
        );

        $this->statistics->incrementMessagesProcessed($event);

        $array = $this->statistics->toArray();
        self::assertSame(0, $array['documents_received']);
    }

    #[Test]
    public function itIncrementsMessagesFailed(): void
    {
        $this->statistics->incrementMessagesFailed();
        $this->statistics->incrementMessagesFailed();

        $string = (string) $this->statistics;
        self::assertStringContainsString('failed: 2', $string);
    }

    #[Test]
    public function itIncrementsAndDecrementsPendingMessages(): void
    {
        $this->statistics->incrementPendingMessages();
        $this->statistics->incrementPendingMessages();

        $array = $this->statistics->toArray();
        self::assertSame(2, $array['pending_messages']);

        $this->statistics->decrementPendingMessages();
        $array = $this->statistics->toArray();
        self::assertSame(1, $array['pending_messages']);

        $this->statistics->decrementPendingMessages();
        $array = $this->statistics->toArray();
        self::assertSame(0, $array['pending_messages']);

        // Should not go below zero
        $this->statistics->decrementPendingMessages();
        $array = $this->statistics->toArray();
        self::assertSame(0, $array['pending_messages']);
    }

    #[Test]
    public function itIncrementsActiveConnectionsAndTotalConnections(): void
    {
        $this->statistics->incrementActiveConnections();
        $this->statistics->incrementActiveConnections();

        $array = $this->statistics->toArray();
        self::assertSame(2, $array['active_connections']);
        self::assertSame(2, $array['total_connections_handled']);
    }

    #[Test]
    public function itDecrementsActiveConnectionsButNotTotal(): void
    {
        $this->statistics->incrementActiveConnections();
        $this->statistics->incrementActiveConnections();
        $this->statistics->decrementActiveConnections();

        $array = $this->statistics->toArray();
        self::assertSame(1, $array['active_connections']);
        self::assertSame(2, $array['total_connections_handled']);

        // Should not go below zero
        $this->statistics->decrementActiveConnections();
        $this->statistics->decrementActiveConnections();

        $array = $this->statistics->toArray();
        self::assertSame(0, $array['active_connections']);
        self::assertSame(2, $array['total_connections_handled']);
    }

    #[Test]
    public function itCalculatesUptime(): void
    {
        $uptime = $this->statistics->getUptime();

        self::assertMatchesRegularExpression('/^\d{2}:\d{2}:\d{2}$/', $uptime);
    }

    #[Test]
    public function itConvertsToArray(): void
    {
        $this->statistics->incrementPing();
        $this->statistics->incrementMessagesReceived();
        $this->statistics->incrementActiveConnections();

        $array = $this->statistics->toArray();

        self::assertArrayHasKey('ping_count', $array);
        self::assertArrayHasKey('pong_count', $array);
        self::assertArrayHasKey('messages_received', $array);
        self::assertArrayHasKey('documents_received', $array);
        self::assertArrayHasKey('pending_messages', $array);
        self::assertArrayHasKey('active_connections', $array);
        self::assertArrayHasKey('total_connections_handled', $array);
        self::assertArrayHasKey('uptime', $array);
        self::assertArrayHasKey('last_message_at', $array);

        self::assertSame(1, $array['ping_count']);
        self::assertSame(1, $array['messages_received']);
        self::assertSame(1, $array['active_connections']);
    }

    #[Test]
    public function itConvertsToString(): void
    {
        $this->statistics->incrementPing();
        $this->statistics->incrementPong();
        $this->statistics->incrementMessagesReceived();
        $this->statistics->incrementDocumentsReceived();
        $this->statistics->incrementActiveConnections();

        $string = (string) $this->statistics;

        self::assertStringContainsString('Uptime=', $string);
        self::assertStringContainsString('Connections=1/1', $string);
        self::assertStringContainsString('Ping=1', $string);
        self::assertStringContainsString('Pong=1', $string);
        self::assertStringContainsString('MessagesReceived=1', $string);
        self::assertStringContainsString('success: 0', $string);
        self::assertStringContainsString('failed: 0', $string);
        self::assertStringContainsString('Documents=1', $string);
    }

    #[Test]
    public function itDisplaysProcessedAndFailedCountsInString(): void
    {
        $event = new CollectDataReceivedEvent(
            'task-123',
            'provider-456',
            [
                'type' => 'raw_result',
                'content' => 'test',
            ]
        );

        $this->statistics->incrementMessagesReceived();
        $this->statistics->incrementMessagesProcessed($event);
        $this->statistics->incrementMessagesProcessed($event);
        $this->statistics->incrementMessagesFailed();

        $string = (string) $this->statistics;

        self::assertStringContainsString('MessagesReceived=1', $string);
        self::assertStringContainsString('success: 2', $string);
        self::assertStringContainsString('failed: 1', $string);
        self::assertStringContainsString('Documents=2', $string);
    }

    #[Test]
    public function itResetsAllStatistics(): void
    {
        $event = new CollectDataReceivedEvent(
            'task-123',
            'provider-456',
            [
                'type' => 'document_refined_result',
                'content' => 'test',
            ]
        );

        $this->statistics->incrementPing();
        $this->statistics->incrementPong();
        $this->statistics->incrementMessagesReceived();
        $this->statistics->incrementMessagesProcessed($event);
        $this->statistics->incrementMessagesFailed();
        $this->statistics->incrementDocumentsReceived();
        $this->statistics->incrementPendingMessages();
        $this->statistics->incrementActiveConnections();

        $this->statistics->reset();

        self::assertSame(
            [
                'ping_count' => 0,
                'pong_count' => 0,
                'messages_received' => 0,
                'documents_received' => 0,
                'pending_messages' => 0,
                'active_connections' => 0,
                'total_connections_handled' => 0,
                'uptime' => '00:00:00',
                'memory_usage' => $this->statistics->getMemoryUsage(),
                'last_message_at' => null,
            ],
            $this->statistics->toArray(),
        );
    }
}
