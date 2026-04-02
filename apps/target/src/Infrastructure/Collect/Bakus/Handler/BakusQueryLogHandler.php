<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Handler;

use App\Domain\Collect\CollectDataHandlerInterface;
use App\Domain\Collect\CollectTaskGatewayInterface;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Domain\SourceActivity\SourceActivityLoggerInterface;
use Psr\Log\LoggerInterface;

class BakusQueryLogHandler implements CollectDataHandlerInterface
{
    private const string MANAGED_TYPE = 'query_log';

    public function __construct(
        private readonly CollectTaskGatewayInterface $collectTaskGateway,
        private readonly SourceActivityLoggerInterface $sourceActivityLogger,
        private readonly SourceActivityGatewayInterface $sourceActivityGateway,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function supports(CollectDataReceivedEvent $event): bool
    {
        return self::MANAGED_TYPE === $event->getType();
    }

    public function __invoke(CollectDataReceivedEvent $event): void
    {
        $this->logger?->info('Processing query log', [
            'collect_task_id' => $event->collectTaskId,
            'provider_task_id' => $event->providerTaskId,
            'provider_name' => 'bakus',
            'data_type' => $event->getType() ?? 'unknown',
            'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
        ]);

        try {
            $collectTask = $this->collectTaskGateway->get($event->collectTaskId);
            $source = $collectTask->getSource();

            $sourceActivity = $this->sourceActivityLogger->logSourceQueryLog($source, $event->data, 'bakus');

            $this->sourceActivityGateway->save($sourceActivity);
        } catch (\Exception $e) {
            $this->logger?->warning('Unable to process query log', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
