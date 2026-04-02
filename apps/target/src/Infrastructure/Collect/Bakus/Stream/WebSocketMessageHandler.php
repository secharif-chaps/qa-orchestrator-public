<?php

namespace App\Infrastructure\Collect\Bakus\Stream;

use App\Domain\Collect\Stream\Event\CollectDataReceivedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Webmozart\Assert\Assert;

readonly class WebSocketMessageHandler
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(
        string $payload,
        string $collectTaskId,
        string $providerTaskId,
    ): ?CollectDataReceivedEvent {
        try {
            $data = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                $this->logger?->warning('Invalid WebSocket message format', [
                    'provider_task_id' => $providerTaskId,
                    'payload' => $payload,
                ]);

                return null;
            }
        } catch (\JsonException $e) {
            $this->logger?->error('Failed to parse WebSocket message', [
                'provider_task_id' => $providerTaskId,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            Assert::keyExists($data, 'type', 'Missing "type" field in WebSocket message');
            Assert::string($data['type'], 'Invalid "type" field in WebSocket message');

            // Ensure all keys are strings
            Assert::allString(array_keys($data), 'Invalid WebSocket message format');

            // At this point we are sure $data is array<string, mixed> and we can safely type it
            /** @var array<string, mixed> $data */
        } catch (\InvalidArgumentException $e) {
            $this->logger?->warning('Invalid WebSocket message format', [
                'provider_task_id' => $providerTaskId,
                'payload' => $payload,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        try {
            $event = new CollectDataReceivedEvent($collectTaskId, $providerTaskId, $data);
            $this->eventDispatcher->dispatch($event);

            return $event;
        } catch (\Throwable $e) {
            $this->logger?->error('Error dispatching CollectDataReceivedEvent', [
                'collect_task_id' => $collectTaskId,
                'provider_task_id' => $providerTaskId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}
