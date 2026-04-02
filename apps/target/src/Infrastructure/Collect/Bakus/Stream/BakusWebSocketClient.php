<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Stream;

use App\Domain\Collect\Stream\CollectDataPullStreamInterface;
use App\Domain\Collect\Stream\Event\CollectDataStreamConnectedEvent;
use App\Domain\Collect\Stream\Event\CollectDataStreamDisconnectedEvent;
use App\Domain\Collect\Stream\Event\CollectStreamCircuitBreakerActivatedEvent;
use App\Domain\Collect\Stream\Exception\AbnormallyCloseStreamException;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use App\Domain\Collect\Stream\Exception\ErrorClientStreamException;
use App\Infrastructure\Collect\Bakus\Client\BakusAbstractClient;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use WebSocket\Client;
use WebSocket\Connection;
use WebSocket\Exception\ExceptionInterface;
use WebSocket\Message\Close;
use WebSocket\Message\Text;

class BakusWebSocketClient extends BakusAbstractClient implements CollectDataPullStreamInterface
{
    use WebSocketUtil;
    private ?Client $client = null;
    private bool $isConnected = false;
    private bool $isReconnecting = false;
    private bool $circuitBreakerActive = false;
    private int $reconnectAttempts = 0;
    private const int DEFAULT_MAX_RECONNECT_ATTEMPTS = 5;
    private const int DEFAULT_MAX_BACKOFF_SECONDS = 60;

    public function __construct(
        private readonly string $websocketUrl,
        /**
         * @var callable(string): Client
         */
        private $clientFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        MessageBusInterface $messageBus,
        private readonly ClockInterface $clock,
        private readonly WebSocketMessageHandler $webSocketMessageHandler,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $maxReconnectAttempts = self::DEFAULT_MAX_RECONNECT_ATTEMPTS,
        private readonly int $maxBackoffSeconds = self::DEFAULT_MAX_BACKOFF_SECONDS,
    ) {
        parent::__construct($messageBus);
    }

    public function connect(string $collectTaskId, string $providerTaskId): bool
    {
        if ($this->isConnected) {
            $this->logger?->warning('Already connected to WebSocket', [
                'provider_task_id' => $providerTaskId,
            ]);

            return false;
        }

        if ($this->isReconnecting) {
            $this->logger?->debug('Reconnection already in progress', [
                'provider_task_id' => $providerTaskId,
            ]);

            return false;
        }

        if ($this->circuitBreakerActive) {
            $this->logger?->error('Circuit breaker is active, cannot connect to WebSocket', [
                'provider_task_id' => $providerTaskId,
            ]);

            throw new CollectDataStreamException('Circuit breaker is active, cannot connect to WebSocket');
        }

        try {
            $websocketUrl = $this->buildWebSocketUrl($providerTaskId);

            $this->logger?->info('Connecting to Bakus WebSocket', [
                'provider_task_id' => $providerTaskId,
                'url' => $websocketUrl,
            ]);

            $this->client = ($this->clientFactory)($websocketUrl)
                ->addHeader('Authorization', $this->getToken()->toAuthenticationHeader())
                ->onHandshake(
                    function (
                        Client $client,
                        Connection $connection,
                        RequestInterface $request,
                        ResponseInterface $response,
                    ) use ($collectTaskId, $providerTaskId) {
                        $this->isConnected = true;

                        $this->logger?->info('WebSocket connection established', [
                            'provider_task_id' => $providerTaskId,
                        ]);

                        $this->eventDispatcher->dispatch(
                            new CollectDataStreamConnectedEvent($collectTaskId, $providerTaskId)
                        );
                    }
                )
                ->onText(
                    function (Client $client, Connection $connection, Text $message) use (
                        $collectTaskId,
                        $providerTaskId
                    ) {
                        ($this->webSocketMessageHandler)($message->getPayload(), $collectTaskId, $providerTaskId);
                    }
                )
                ->onClose(
                    function (Client $client, Connection $connection, Close $message) use (
                        $collectTaskId,
                        $providerTaskId
                    ) {
                        $this->handleConnectionClosed(
                            $message->getCloseStatus(),
                            $message->getContent(),
                            $collectTaskId,
                            $providerTaskId,
                        );
                    }
                )
                ->onError(
                    function (Client $client, ?Connection $connection, ExceptionInterface $exception) use (
                        $providerTaskId
                    ) {
                        $this->logger?->error('WebSocket error occurred', [
                            'provider_task_id' => $providerTaskId,
                            'error' => $exception->getMessage(),
                        ]);

                        throw new ErrorClientStreamException(
                            $this->reconnectAttempts,
                            $this->reconnectAttempts < $this->maxReconnectAttempts,
                            'WebSocket error: ' . $exception->getMessage(),
                            0,
                            $exception,
                        );
                    }
                );

            // Initiate the connection
            $this->client->start();

            return true;
        } catch (AbnormallyCloseStreamException $e) {
            $this->client->disconnect();
            $this->client->stop();
            $this->client = null;

            if ($e->willRetry) {
                return $this->attemptReconnection($collectTaskId, $providerTaskId, $e->codeMeaning);
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->isConnected = false;

            throw new CollectDataStreamException(\sprintf(
                'Failed to connect to WebSocket: %s',
                $e->getMessage()
            ), 0, $e);
        } finally {
            $this->client?->disconnect();
            $this->client?->stop();
            $this->client = null;
        }
    }

    private function buildWebSocketUrl(string $providerTaskId): string
    {
        $baseUrl = rtrim($this->websocketUrl, '/');

        return \sprintf('%s/ws/queries/%s', $baseUrl, urlencode($providerTaskId));
    }

    private function handleConnectionClosed(
        ?int $code,
        ?string $reason,
        string $collectTaskId,
        string $providerTaskId,
    ): void {
        $this->isConnected = false;

        $originalReason = $reason;
        if (!empty($code) && empty($reason)) {
            $reason = $this->getCloseCodeMeaning($code);
        }

        $this->eventDispatcher->dispatch(
            new CollectDataStreamDisconnectedEvent($collectTaskId, $providerTaskId, $code, $reason)
        );

        if ($code && 1000 !== $code) {
            throw new AbnormallyCloseStreamException(
                $this->reconnectAttempts,
                $this->reconnectAttempts < $this->maxReconnectAttempts,
                $this->getCloseCodeMeaning(
                    $code
                ),
                'WebSocket connection closed abnormally with code ' . $code . ' (' . $originalReason . ')',
                $code,
            );
        }

        $this->logger?->info('WebSocket connection closed normally', [
            'provider_task_id' => $providerTaskId,
            'code' => $code,
            'code_meaning' => $reason,
            'reason' => $originalReason ?: 'No reason provided',
        ]);
    }

    public function disconnect(): void
    {
        $this->isConnected = false;

        if ($this->client) {
            $this->logger?->info('Closing WebSocket connection');
            $this->client->disconnect();
            $this->client->stop();
            $this->client = null;
        }
    }

    private function attemptReconnection(string $collectTaskId, string $providerTaskId, ?string $lastError): bool
    {
        if ($this->isReconnecting) {
            return false;
        }

        if (\PHP_SAPI !== 'cli') {
            $this->logger?->warning('Reconnection attempts are only supported in CLI environment', [
                'collect_task_id' => $collectTaskId,
                'provider_task_id' => $providerTaskId,
            ]);

            return false;
        }

        if ($this->client instanceof Client) {
            $this->logger?->info('Resetting existing WebSocket client before reconnection', [
                'collect_task_id' => $collectTaskId,
                'provider_task_id' => $providerTaskId,
            ]);

            $this->client->disconnect();
            $this->client->stop();
            $this->client = null;
        }

        if ($this->reconnectAttempts >= $this->maxReconnectAttempts) {
            $this->logger?->critical('WebSocket max reconnection attempts reached', [
                'collect_task_id' => $collectTaskId,
                'provider_task_id' => $providerTaskId,
                'attempts' => $this->reconnectAttempts,
                'last_error' => $lastError,
            ]);

            // throw circuit breaker activation event/exception
            $this->handleCircuitBreakerActivation($collectTaskId, $providerTaskId, $this->reconnectAttempts);
        }

        $this->isReconnecting = true;
        ++$this->reconnectAttempts;

        $backoffSeconds = min($this->maxBackoffSeconds, (int) 2 ** ($this->reconnectAttempts - 1));

        $this->logger?->warning('WebSocket connection lost, attempting reconnection', [
            'collect_task_id' => $collectTaskId,
            'provider_task_id' => $providerTaskId,
            'attempt' => $this->reconnectAttempts,
            'backoff_seconds' => $backoffSeconds,
            'last_error' => $lastError,
        ]);

        $this->clock->sleep($backoffSeconds);

        try {
            $this->isReconnecting = false;

            return $this->connect($collectTaskId, $providerTaskId);
        } catch (\Throwable $e) {
            $this->logger?->error('Reconnection attempt failed', [
                'collect_task_id' => $collectTaskId,
                'provider_task_id' => $providerTaskId,
                'attempt' => $this->reconnectAttempts,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function handleCircuitBreakerActivation(string $collectTaskId, string $providerTaskId, int $attempts): void
    {
        $this->circuitBreakerActive = true;
        $this->isConnected = false;

        $this->logger?->critical('WebSocket circuit breaker activated', [
            'collect_task_id' => $collectTaskId,
            'provider_task_id' => $providerTaskId,
            'failed_attempts' => $attempts,
        ]);

        $this->eventDispatcher->dispatch(new CollectStreamCircuitBreakerActivatedEvent(
            $collectTaskId,
            $providerTaskId,
            $attempts,
        ));

        throw new CollectDataStreamException(
            'WebSocket circuit breaker activated after ' . $attempts . ' failed reconnection attempts'
        );
    }

    public function isConnected(): bool
    {
        return $this->isConnected;
    }
}
