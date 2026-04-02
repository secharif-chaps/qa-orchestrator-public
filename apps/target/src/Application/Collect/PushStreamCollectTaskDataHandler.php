<?php

declare(strict_types=1);

namespace App\Application\Collect;

use App\Domain\Collect\Stream\CollectDataPushStreamInterface;
use App\Domain\Collect\Stream\Exception\CollectDataStreamException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Initiates an incoming WebSocket connection to receive real-time collect task data.
 *
 * This action represents the "Push" mode where the data provider pushes data to our application
 * via a WebSocket connection.
 */
#[AsMessageHandler]
readonly class PushStreamCollectTaskDataHandler
{
    public function __construct(
        private CollectDataPushStreamInterface $streamServer,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(PushStreamCollectTaskDataAction $action): bool
    {
        try {
            return $this->streamServer->listen();
        } catch (CollectDataStreamException $e) {
            $this->logger?->error('WebSocket server error: ' . $e->getMessage());

            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->error('WebSocket server failed: ' . $e->getMessage());

            throw new CollectDataStreamException($e->getMessage(), 0, $e);
        } finally {
            $this->streamServer->stop();
        }
    }
}
