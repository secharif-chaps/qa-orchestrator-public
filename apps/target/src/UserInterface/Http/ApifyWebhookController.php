<?php

declare(strict_types=1);

namespace App\UserInterface\Http;

use App\Application\Collect\Task\UpdateTaskStatusAction;
use App\Infrastructure\Collect\Apify\ApifyStatusMapper;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[AsController]
class ApifyWebhookController extends AbstractController
{
    use HandleTrait;

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

        $payload = $this->decodePayload($request);
        $status = $this->extractStatus($payload);

        $collectTaskStatus = $this->statusMapper->mapStatus($status);

        $this->logger?->info('Apify webhook received', [
            'collect_task_id' => $collectTaskId,
            'event_type' => $payload['eventType'] ?? null,
            'apify_status' => $status,
            'mapped_status' => $collectTaskStatus->value,
        ]);

        $this->handle(new UpdateTaskStatusAction(collectTaskId: $collectTaskId, status: $collectTaskStatus));

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
}
