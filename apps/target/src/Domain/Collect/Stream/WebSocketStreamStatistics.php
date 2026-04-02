<?php

declare(strict_types=1);

namespace App\Domain\Collect\Stream;

use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;

class WebSocketStreamStatistics implements \Stringable
{
    private int $pingCount = 0;
    private int $pongCount = 0;
    private int $messagesReceived = 0;
    private int $messagesProcessed = 0;
    private int $messagesFailed = 0;
    private int $documentsReceived = 0;
    private int $pendingMessages = 0;
    private int $activeConnections = 0;
    private int $totalConnectionsHandled = 0;
    private \DateTimeImmutable $startedAt;
    private ?\DateTimeImmutable $lastMessageAt = null;

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable();
    }

    public function incrementPing(): void
    {
        ++$this->pingCount;
    }

    public function incrementPong(): void
    {
        ++$this->pongCount;
    }

    public function incrementMessagesReceived(): void
    {
        ++$this->messagesReceived;
        $this->lastMessageAt = new \DateTimeImmutable();
    }

    public function incrementDocumentsReceived(): void
    {
        ++$this->documentsReceived;
    }

    public function incrementMessagesProcessed(CollectDataReceivedEvent $event): void
    {
        ++$this->messagesProcessed;

        if ($event->isDocument()) {
            $this->incrementDocumentsReceived();
        }
    }

    public function incrementMessagesFailed(): void
    {
        ++$this->messagesFailed;
    }

    public function incrementPendingMessages(): void
    {
        ++$this->pendingMessages;
    }

    public function decrementPendingMessages(): void
    {
        if ($this->pendingMessages > 0) {
            --$this->pendingMessages;
        }
    }

    public function incrementActiveConnections(): void
    {
        ++$this->activeConnections;
        ++$this->totalConnectionsHandled;
    }

    public function decrementActiveConnections(): void
    {
        if ($this->activeConnections > 0) {
            --$this->activeConnections;
        }
    }

    public function getUptime(): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->startedAt);

        return \sprintf('%02d:%02d:%02d', $diff->h + ($diff->days * 24), $diff->i, $diff->s);
    }

    public function getMemoryUsage(): string
    {
        $bytes = memory_get_usage(true);

        return \sprintf('%.1f MB', $bytes / 1024 / 1024);
    }

    /**
     * @return array{
     *     ping_count: int,
     *     pong_count: int,
     *     messages_received: int,
     *     documents_received: int,
     *     pending_messages: int,
     *     active_connections: int,
     *     total_connections_handled: int,
     *     uptime: string,
     *     memory_usage: string,
     *     last_message_at: string|null,
     * }
     */
    public function toArray(): array
    {
        return [
            'ping_count' => $this->pingCount,
            'pong_count' => $this->pongCount,
            'messages_received' => $this->messagesReceived,
            'documents_received' => $this->documentsReceived,
            'pending_messages' => $this->pendingMessages,
            'active_connections' => $this->activeConnections,
            'total_connections_handled' => $this->totalConnectionsHandled,
            'uptime' => $this->getUptime(),
            'memory_usage' => $this->getMemoryUsage(),
            'last_message_at' => $this->lastMessageAt?->format('Y-m-d H:i:s'),
        ];
    }

    public function __toString(): string
    {
        return \sprintf(
            'Statistics: Uptime=%s | Memory=%s | Connections=%d/%d | Ping=%d | Pong=%d | MessagesReceived=%d (success: %d, failed: %d) | Documents=%d | Pending=%d | Last=%s',
            $this->getUptime(),
            $this->getMemoryUsage(),
            $this->activeConnections,
            $this->totalConnectionsHandled,
            $this->pingCount,
            $this->pongCount,
            $this->messagesReceived,
            $this->messagesProcessed,
            $this->messagesFailed,
            $this->documentsReceived,
            $this->pendingMessages,
            $this->lastMessageAt?->format('H:i:s') ?? 'N/A'
        );
    }

    public function reset(): void
    {
        $this->pingCount = 0;
        $this->pongCount = 0;
        $this->messagesReceived = 0;
        $this->documentsReceived = 0;
        $this->pendingMessages = 0;
        $this->activeConnections = 0;
        $this->totalConnectionsHandled = 0;
        $this->startedAt = new \DateTimeImmutable();
        $this->lastMessageAt = null;
    }
}
