<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Stream;

use App\Domain\Collect\Stream\CollectDataPushStreamInterface;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\Collect\Stream\Event\CollectDataStreamDisconnectedEvent;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Domain\Collect\Stream\WebSocketStreamStatistics;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use WebSocket\Connection;
use WebSocket\Exception\ExceptionInterface;
use WebSocket\Message\Close;
use WebSocket\Message\Ping;
use WebSocket\Message\Pong;
use WebSocket\Message\Text;
use WebSocket\Server;

class BakusWebSocketPush implements CollectDataPushStreamInterface
{
    use WebSocketUtil;
    private bool $isListening = false;
    private WebSocketStreamStatistics $statistics;
    private float $lastStatisticsDisplayTime = 0;
    private const int STATISTICS_DISPLAY_INTERVAL_SECONDS = 60;

    /** @var array<string, bool> */
    private array $trackedConnections = [];

    public function __construct(
        private readonly Server $server,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly WebSocketMessageHandler $webSocketMessageHandler,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->statistics = new WebSocketStreamStatistics();
    }

    public function listen(): bool
    {
        try {
            $this->isListening = true;
            $this->lastStatisticsDisplayTime = microtime(true);

            $this->server->onText(
                function (Server $server, Connection $connection, Text $message) {
                    $this->handleText($connection, $message);
                }
            )
            ->onPing(
                function (Server $server, Connection $connection, Ping $message) {
                    $this->statistics->incrementPing();
                }
            )
            ->onPong(
                function (Server $server, Connection $connection, Pong $message) {
                    $this->statistics->incrementPong();
                }
            )
            ->onClose(
                function (Server $server, Connection $connection, Close $message) {
                    $collectTaskId = $connection->getMeta('collect_task_id');
                    $providerTaskId = $connection->getMeta('provider_task_id');
                    $connectionId = $connection->getMeta('connection_id');

                    if (\is_string($collectTaskId) && \is_string($providerTaskId) && \is_string($connectionId)) {
                        $this->handleConnectionClosed(
                            $message->getCloseStatus(),
                            $message->getContent(),
                            $collectTaskId,
                            $providerTaskId,
                            $connectionId,
                        );
                    }

                    $connection->setMeta('collect_task_id', null);
                    $connection->setMeta('provider_task_id', null);
                    $connection->setMeta('connection_id', null);

                    $this->entityManager->clear();
                }
            )
            ->onError(
                function (Server $server, ?Connection $connection, ExceptionInterface $exception) {
                    $this->handleError($connection, $exception);
                    $this->entityManager->clear();
                }
            )
            ->onTick(
                function (Server $server) {
                    $currentTime = microtime(true);
                    if ($currentTime - $this->lastStatisticsDisplayTime >= self::STATISTICS_DISPLAY_INTERVAL_SECONDS) {
                        $this->displayStatistics();
                        $this->lastStatisticsDisplayTime = $currentTime;
                    }
                }
            );

            // Initiate the connection
            $this->server->start();

            return true;
        } catch (\Throwable $e) {
            throw new CollectDataStreamException(\sprintf(
                'Failed to connect to WebSocket: %s',
                $e->getMessage()
            ), 0, $e);
        } finally {
            $this->isListening = false;
            $this->server->disconnect();
            $this->server->stop();
        }
    }

    private function handleConnectionClosed(
        ?int $code,
        ?string $reason,
        string $collectTaskId,
        string $providerTaskId,
        string $connectionId,
    ): void {
        // Decrement active connections if this connection was tracked
        if (isset($this->trackedConnections[$connectionId])) {
            $this->statistics->decrementActiveConnections();
            unset($this->trackedConnections[$connectionId]);
        }

        $originalReason = $reason;
        if (!empty($code) && empty($reason)) {
            $reason = $this->getCloseCodeMeaning($code);
        }

        $this->eventDispatcher->dispatch(
            new CollectDataStreamDisconnectedEvent($collectTaskId, $providerTaskId, $code, $reason)
        );

        if ($code && 1000 !== $code) {
            $this->logger?->info(
                'WebSocket connection closed abnormally with code ' . $code . ' (' . $originalReason . ')',
                [
                    'provider_task_id' => $providerTaskId,
                    'code' => $code,
                    'code_meaning' => $reason,
                    'reason' => $originalReason ?: 'No reason provided',
                ]
            );
        } else {
            $this->logger?->info('WebSocket connection closed normally', [
                'provider_task_id' => $providerTaskId,
                'code' => $code,
                'code_meaning' => $reason,
                'reason' => $originalReason ?: 'No reason provided',
            ]);
        }
    }

    private function handleText(Connection $connection, Text $message): void
    {
        $collectTaskId = $connection->getMeta('collect_task_id');
        if (!\is_string($collectTaskId)) {
            $this->logger?->warning('Received message without associated collect task ID, ignoring');

            return;
        }

        $providerTaskId = $connection->getMeta('provider_task_id');
        if (!\is_string($providerTaskId)) {
            $this->logger?->warning('Received message without associated provider task ID, ignoring');

            return;
        }

        // Track new connection if not already tracked
        $connectionId = $connection->getMeta('connection_id');
        if (!\is_string($connectionId)) {
            $this->logger?->warning('Received message without associated connection ID, ignoring');

            return;
        }

        if (!isset($this->trackedConnections[$connectionId])) {
            $this->trackedConnections[$connectionId] = true;
            $this->statistics->incrementActiveConnections();
        }

        // Increment statistics
        $this->statistics->incrementMessagesReceived();

        // Check if it's a document message
        $payload = $message->getPayload();

        $event = ($this->webSocketMessageHandler)($payload, $collectTaskId, $providerTaskId);
        if ($event instanceof CollectDataReceivedEvent) {
            $this->statistics->incrementMessagesProcessed($event);
        } elseif (null === $event) {
            $this->statistics->incrementMessagesFailed();
        }
        $this->entityManager->clear();
    }

    private function handleError(?Connection $connection, ExceptionInterface $exception): void
    {
        if (null === $connection) {
            $this->logger?->error('WebSocket error occurred without connection', [
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        $this->logger?->error('WebSocket error occurred', [
            'collect_task_id' => $connection->getMeta('collect_task_id'),
            'provider_task_id' => $connection->getMeta('provider_task_id'),
            'connection_id' => $connection->getMeta('connection_id'),
            'error' => $exception->getMessage(),
        ]);

        $connection->setMeta('collect_task_id', null);
        $connection->setMeta('provider_task_id', null);
        $connection->setMeta('connection_id', null);
    }

    public function stop(): void
    {
        $this->logger?->info('Closing WebSocket server connection');
        $this->isListening = false;
        $this->server->disconnect();
        $this->server->stop();
    }

    public function isListening(): bool
    {
        return $this->isListening;
    }

    public function getStatistics(): WebSocketStreamStatistics
    {
        return $this->statistics;
    }

    private function displayStatistics(): void
    {
        $this->logger?->info((string) $this->statistics);
    }
}
