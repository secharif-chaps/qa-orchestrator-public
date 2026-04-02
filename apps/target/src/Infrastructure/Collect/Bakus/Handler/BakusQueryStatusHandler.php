<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Bakus\Handler;

use App\Application\Collect\Task\UpdateTaskStatusAction;
use App\Domain\Collect\CollectDataHandlerInterface;
use App\Domain\Collect\StatusMapperInterface;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Webmozart\Assert\Assert;

class BakusQueryStatusHandler implements CollectDataHandlerInterface
{
    private const string MANAGED_TYPE = 'query_status';

    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly StatusMapperInterface $statusMapper,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function supports(CollectDataReceivedEvent $event): bool
    {
        return self::MANAGED_TYPE === $event->getType();
    }

    public function __invoke(CollectDataReceivedEvent $event): void
    {
        $this->logger?->info('Processing query status', [
            'collect_task_id' => $event->collectTaskId,
            'provider_task_id' => $event->providerTaskId,
            'provider_name' => 'bakus',
            'data_type' => $event->getType() ?? 'unknown',
            'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
        ]);
        $data = $event->data;
        try {
            Assert::keyExists($data, 'state');
            Assert::string($data['state']);
            $status = $this->statusMapper->mapStatus($data['state']);
            $this->messageBus->dispatch(new UpdateTaskStatusAction($event->collectTaskId, $status));
        } catch (\Exception $e) {
            $this->logger?->warning('Unable to process query status', [
                'collect_task_id' => $event->collectTaskId,
                'provider_task_id' => $event->providerTaskId,
                'provider_name' => 'bakus',
                'data_type' => $event->getType() ?? 'unknown',
                'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
