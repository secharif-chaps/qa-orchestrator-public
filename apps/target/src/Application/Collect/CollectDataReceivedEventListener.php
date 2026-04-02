<?php

declare(strict_types=1);

namespace App\Application\Collect;

use App\Domain\Collect\CollectDataHandlerInterface;
use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener(event: CollectDataReceivedEvent::class)]
readonly class CollectDataReceivedEventListener
{
    /**
     * @param iterable<CollectDataHandlerInterface> $collectDataHandlers
     */
    public function __construct(
        #[AutowireIterator('collect_data_handler')]
        private iterable $collectDataHandlers,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(CollectDataReceivedEvent $event): void
    {
        $this->logger?->debug('Collect data received', [
            'collect_task_id' => $event->collectTaskId,
            'provider_task_id' => $event->providerTaskId,
            'data_size' => \count($event->data),
            'received_at' => $event->receivedAt->format('Y-m-d H:i:s'),
        ]);

        $handlersCalled = 0;
        $registeredHandlers = 0;
        foreach ($this->collectDataHandlers as $handler) {
            if ($handler->supports($event)) {
                $this->logger?->info('Handling collect data with handler', [
                    'collect_task_id' => $event->collectTaskId,
                    'handler_class' => $handler::class,
                ]);

                try {
                    ++$handlersCalled;
                    $handler($event);
                } catch (\Throwable $e) {
                    $this->logger?->error('Error occurred while handling collect data', [
                        'collect_task_id' => $event->collectTaskId,
                        'handler_class' => $handler::class,
                        'error_message' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                    ]);

                    $this->logger?->debug('Collect data handler error trace', [
                        'collect_task_id' => $event->collectTaskId,
                        'handler_class' => $handler::class,
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
            ++$registeredHandlers;
        }

        if (0 === $handlersCalled) {
            $type = $event->getType() ?? 'unknown';

            $this->logger?->warning(
                'No collect data handlers processed the received data for the given type "' . $type . '"',
                [
                    'collect_task_id' => $event->collectTaskId,
                    'provider_task_id' => $event->providerTaskId,
                    'total_handlers_checked' => $registeredHandlers,
                    'type' => $type,
                    'event' => $event,
                ]
            );
        }
    }
}
