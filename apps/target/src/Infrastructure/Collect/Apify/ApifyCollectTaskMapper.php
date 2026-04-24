<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Application\Collect\Auth\GenerateCollectTaskTokenAction;
use App\Domain\Collect\ApifyInputTemplate;
use App\Domain\Collect\CollectTask;
use App\Domain\Collect\Exception\ApifyConfigurationException;
use App\Domain\Collect\Exception\NotSupportedCollectorException;
use App\Domain\Source\Source;
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
        private readonly ApifyInputInterpolator $interpolator,
        private readonly ApifyInputTemplateProvider $templateProvider,
        private MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function mapToActorRun(CollectTask $collectTask): ApifyActorRunConfig
    {
        $source = $collectTask->getSource();
        $sourceType = $source->getType();
        $apifyActorId = $this->resolveApifyActorId($sourceType);

        $input = $this->buildInput($collectTask, $apifyActorId);
        $queryParams = $this->buildQueryParams($collectTask);

        return new ApifyActorRunConfig(apifyActorId: $apifyActorId, input: $input, queryParams: $queryParams);
    }

    private function resolveApifyActorId(SourceType $sourceType): string
    {
        $apifyActorId = $this->actorMapping[$sourceType->value] ?? null;

        if (null === $apifyActorId) {
            throw new NotSupportedCollectorException(\sprintf(
                'No Apify actor configured for source type: %s',
                $sourceType->value,
            ));
        }

        // The compound providerTaskId uses ":" as a separator (see ApifyRunReference).
        // Apify-issued actor IDs never contain ":", but misconfigured YAML could.
        // Normalize here to guarantee round-trip parsing in fromProviderTaskId().
        if (str_contains($apifyActorId, ApifyRunReference::SEPARATOR)) {
            $normalized = str_replace(ApifyRunReference::SEPARATOR, '_', $apifyActorId);
            $this->logger?->warning(
                'Apify actor ID contains reserved separator ":"; normalizing for providerTaskId encoding.',
                [
                    'source_type' => $sourceType->value,
                    'apify_actor_id_raw' => $apifyActorId,
                    'apify_actor_id_normalized' => $normalized,
                ],
            );
            $apifyActorId = $normalized;
        }

        return $apifyActorId;
    }

    /**
     * Build the actor input payload from the collect task source.
     *
     * Uses input templates for known actors, falls back to legacy input for unknown ones.
     *
     * @return array<string, mixed>
     */
    private function buildInput(CollectTask $collectTask, string $apifyActorId): array
    {
        $source = $collectTask->getSource();

        // Try to get template for this actor
        $template = $this->templateProvider->getTemplateForActor($apifyActorId);

        if (null === $template) {
            // Fallback for actors without templates (backward compat)
            return $this->buildLegacyInput($source);
        }

        // Interpolate variables from template
        $sourceConfig = $source->getParameters() ?? [];
        $input = $this->interpolator->interpolate($template->defaults, $source, $sourceConfig);

        // Validate required fields are present
        $this->validateInput($input, $template);

        return $input;
    }

    /**
     * Legacy input builder for backward compatibility with actors without templates.
     *
     * @return array<string, mixed>
     */
    private function buildLegacyInput(Source $source): array
    {
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
     * Validate that required fields exist in the input.
     *
     * @param array<string, mixed> $input Final input array
     */
    private function validateInput(array $input, ApifyInputTemplate $template): void
    {
        foreach ($template->getRequiredFields() as $field) {
            if (!isset($input[$field])) {
                throw ApifyConfigurationException::missingRequiredField($field);
            }

            if ('' === $input[$field]) {
                throw ApifyConfigurationException::missingRequiredField($field);
            }
        }
    }

    /**
     * Build query parameters including webhook configuration and cost limit.
     *
     * @return array<string, string>
     */
    private function buildQueryParams(CollectTask $collectTask): array
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
