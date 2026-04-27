<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Collect\Apify\FetchApifyDatasetAction;
use App\Application\Collect\Task\UpdateTaskStatusAction;
use App\Domain\Collect\ApifyRunCost;
use App\Domain\Collect\CollectTaskStatus;
use App\Infrastructure\Collect\Apify\ApifyStatusMapper;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ApifyWebhookController extends AbstractController
{
    public function __construct(
        private readonly ApifyStatusMapper $statusMapper,
        private MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/api/apify/webhook', name: 'apify_webhook', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        $collectTaskId = $request->attributes->getString('collect_task_id');
        if ('' === $collectTaskId) {
            throw new BadRequestHttpException('Missing collect_task_id from authentication');
        }

        $dispatchId = $request->headers->get('X-Apify-Webhook-Dispatch-Id');
        $payload = $this->decodePayload($request);
        $status = $this->extractStatus($payload);

        $collectTaskStatus = $this->statusMapper->mapStatus($status);

        $this->logger?->info('Apify webhook received', [
            'collect_task_id' => $collectTaskId,
            'apify_dispatch_id' => $dispatchId,
            'event_type' => $payload['eventType'] ?? null,
            'apify_status' => $status,
            'mapped_status' => $collectTaskStatus->value,
        ]);

        // If run succeeded, dispatch dataset fetch action; otherwise dispatch status update
        if (CollectTaskStatus::COMPLETED === $collectTaskStatus) {
            $datasetId = $this->extractDatasetId($payload);
            $runCost = $this->extractRunCost($payload);
            $this->messageBus->dispatch(
                new FetchApifyDatasetAction($collectTaskId, $datasetId, $runCost),
                [new DispatchAfterCurrentBusStamp()]
            );

            $this->logger?->info('Dispatched FetchApifyDatasetAction', [
                'collect_task_id' => $collectTaskId,
                'dataset_id' => $datasetId,
                'cost_usd' => $runCost?->costUsd,
                'compute_units' => $runCost?->computeUnits,
                'provider_name' => 'apify',
            ]);
        } else {
            $this->messageBus->dispatch(
                new UpdateTaskStatusAction(collectTaskId: $collectTaskId, status: $collectTaskStatus),
                [new DispatchAfterCurrentBusStamp()]
            );

            $this->logger?->info('Dispatched UpdateTaskStatusAction', [
                'collect_task_id' => $collectTaskId,
                'status' => $collectTaskStatus->value,
                'provider_name' => 'apify',
            ]);
        }

        return new JsonResponse([
            'status' => 'ok',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request): array
    {
        $content = $request->getContent();
        if ('' === $content) {
            throw new BadRequestHttpException('Empty request body');
        }

        try {
            $payload = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BadRequestHttpException('Invalid JSON payload: ' . $e->getMessage());
        }

        if (!\is_array($payload) || array_is_list($payload)) {
            throw new BadRequestHttpException('Invalid payload format');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * Extract the run status from the Apify webhook payload.
     *
     * Apify webhook payloads contain the run status in resource.status.
     *
     * @param array<string, mixed> $payload
     */
    private function extractStatus(array $payload): string
    {
        $resource = $payload['resource'] ?? null;
        $status = \is_array($resource) ? ($resource['status'] ?? null) : null;

        if (!\is_string($status) || '' === $status) {
            $this->logger?->error('Missing or invalid status in Apify webhook payload', [
                'payload' => $payload,
            ]);

            throw new BadRequestHttpException('Missing or invalid "resource.status" in webhook payload');
        }

        return $status;
    }

    /**
     * Extract cost metrics from the Apify webhook payload.
     *
     * Returns null when cost data is absent (e.g. older Apify versions or test webhooks).
     *
     * @param array<string, mixed> $payload
     */
    private function extractRunCost(array $payload): ?ApifyRunCost
    {
        $resource = $payload['resource'] ?? null;
        if (!\is_array($resource)) {
            return null;
        }

        if (!isset($resource['usage']) && !isset($resource['usageUsd'])) {
            return null;
        }

        /** @var array<string, mixed> $usage */
        $usage = \is_array($resource['usage'] ?? null) ? $resource['usage'] : [];

        /** @var array<string, mixed> $usageUsd */
        $usageUsd = \is_array($resource['usageUsd'] ?? null) ? $resource['usageUsd'] : [];

        $computeUnits = $usage['ACTOR_COMPUTE_UNITS'] ?? 0.0;
        $costUsd = $usageUsd['ACTOR_COMPUTE_UNITS'] ?? 0.0;

        $durationSeconds = null;
        $startedAt = $resource['startedAt'] ?? null;
        $finishedAt = $resource['finishedAt'] ?? null;

        if (\is_string($startedAt) && \is_string($finishedAt)) {
            try {
                $start = new \DateTimeImmutable($startedAt);
                $end = new \DateTimeImmutable($finishedAt);
                $durationSeconds = max(0, (int) $end->getTimestamp() - (int) $start->getTimestamp());
            } catch (\Exception) {
                // Non-fatal: duration stays null
            }
        }

        return new ApifyRunCost(
            computeUnits: \is_float($computeUnits) || \is_int($computeUnits) ? (float) $computeUnits : 0.0,
            costUsd: \is_float($costUsd) || \is_int($costUsd) ? (float) $costUsd : 0.0,
            durationSeconds: $durationSeconds,
        );
    }

    /**
     * Extract the dataset ID from the Apify webhook payload.
     *
     * Apify webhook payloads contain the dataset ID in resource.defaultDatasetId.
     *
     * @param array<string, mixed> $payload
     */
    private function extractDatasetId(array $payload): string
    {
        $resource = $payload['resource'] ?? null;
        $datasetId = \is_array($resource) ? ($resource['defaultDatasetId'] ?? null) : null;

        if (!\is_string($datasetId) || '' === $datasetId) {
            $this->logger?->error('Missing or invalid dataset ID in Apify webhook payload', [
                'payload' => $payload,
            ]);

            throw new BadRequestHttpException('Missing or invalid "resource.defaultDatasetId" in webhook payload');
        }

        return $datasetId;
    }
}
