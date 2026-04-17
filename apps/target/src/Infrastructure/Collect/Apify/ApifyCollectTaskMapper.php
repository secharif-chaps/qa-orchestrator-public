<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Application\Collect\Auth\GenerateCollectTaskTokenAction;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Source\SourceType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

class ApifyCollectTaskMapper
{
    use HandleTrait;

    public function __construct(
        #[Autowire('%app.apify.webhook_url%')]
        private readonly string $webhookBaseUrl,
        #[Autowire('%app.apify.max_total_charge_usd%')]
        private readonly string $maxTotalChargeUsd,
        /**
         * @var array<string, string> Maps SourceType values to Apify actor IDs
         */
        #[Autowire('%app.apify.actor_mapping%')]
        private readonly array $actorMapping,
        private MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function mapToActorRun(CollectTask $collectTask): ApifyActorRunConfig
    {
        $source = $collectTask->getSource();
        $sourceType = $source->getType();
        $actorId = $this->resolveActorId($sourceType);

        $input = $this->buildInput($collectTask);
        $queryParams = $this->buildQueryParams($collectTask, $actorId);

        return new ApifyActorRunConfig(actorId: $actorId, input: $input, queryParams: $queryParams);
    }

    private function resolveActorId(SourceType $sourceType): string
    {
        $actorId = $this->actorMapping[$sourceType->value] ?? null;

        if (null === $actorId) {
            throw new NotSupportedCollectorException(\sprintf(
                'No Apify actor configured for source type: %s',
                $sourceType->value,
            ));
        }

        // The compound providerTaskId uses ":" as a separator (see ApifyRunReference).
        // Apify-issued actor IDs never contain ":", but misconfigured YAML could.
        // Normalize here to guarantee round-trip parsing in fromProviderTaskId().
        if (str_contains($actorId, ApifyRunReference::SEPARATOR)) {
            $normalized = str_replace(ApifyRunReference::SEPARATOR, '_', $actorId);
            $this->logger?->warning(
                'Apify actorId contains reserved separator ":"; normalizing for providerTaskId encoding.',
                [
                    'source_type' => $sourceType->value,
                    'actor_id_raw' => $actorId,
                    'actor_id_normalized' => $normalized,
                ],
            );
            $actorId = $normalized;
        }

        return $actorId;
    }

    /**
     * Build the actor input payload from the collect task source.
     *
     * @return array<string, mixed>
     */
    private function buildInput(CollectTask $collectTask): array
    {
        $source = $collectTask->getSource();

        $input = [
            'url' => $source->getUrl(),
        ];

        // Merge source parameters into the input if available
        $sourceParameters = $source->getParameters();
        if (null !== $sourceParameters) {
            $input = array_merge($input, $sourceParameters);
        }

        return $input;
    }

    /**
     * Build query parameters including webhook configuration and cost limit.
     *
     * @return array<string, string>
     */
    private function buildQueryParams(CollectTask $collectTask, string $actorId): array
    {
        $params = [];

        if ('' !== $this->maxTotalChargeUsd) {
            $params['maxTotalChargeUsd'] = $this->maxTotalChargeUsd;
        }

        if ('' !== $this->webhookBaseUrl) {
            $webhooks = $this->buildWebhooksParam($collectTask);
            if ('' !== $webhooks) {
                $params['webhooks'] = $webhooks;
            }
        }

        return $params;
    }

    /**
     * Build the base64-encoded webhooks query parameter for Apify ad-hoc webhooks.
     */
    private function buildWebhooksParam(CollectTask $collectTask): string
    {
        $collectTaskId = $collectTask->getId();
        if (null === $collectTaskId) {
            throw new \RuntimeException('Collect task ID is required for webhook authentication');
        }

        /** @var string $token */
        $token = $this->handle(new GenerateCollectTaskTokenAction($collectTaskId));

        $requestUrl = \sprintf('%s/api/apify/webhook?token=%s', rtrim($this->webhookBaseUrl, '/'), $token);

        $webhookConfig = [
            [
                'eventTypes' => [
                    'ACTOR.RUN.SUCCEEDED',
                    'ACTOR.RUN.FAILED',
                    'ACTOR.RUN.TIMED_OUT',
                    'ACTOR.RUN.ABORTED',
                ],
                'requestUrl' => $requestUrl,
            ],
        ];

        return base64_encode(json_encode($webhookConfig, \JSON_THROW_ON_ERROR));
    }

    /**
     * Returns the actor mapping configuration.
     *
     * @return array<string, string>
     */
    public function getActorMapping(): array
    {
        return $this->actorMapping;
    }
}
