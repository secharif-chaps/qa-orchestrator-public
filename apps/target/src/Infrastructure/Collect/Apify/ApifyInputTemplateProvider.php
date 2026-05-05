<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Domain\Collect\ApifyInputTemplate;
use App\Domain\Collect\Exception\ApifyConfigurationException;
use App\Domain\Source\SourceType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Webmozart\Assert\Assert;

readonly class ApifyInputTemplateProvider
{
    /** @var array<string, array<string, mixed>> */
    private array $templatesConfig;

    /**
     * @param array<string, array<string, mixed>> $inputTemplates Input templates from config
     */
    public function __construct(#[Autowire('%app.apify.input_templates%')] array $inputTemplates)
    {
        $this->templatesConfig = $inputTemplates;
    }

    /**
     * Get template for a specific Apify actor ID.
     *
     * @param string $apifyActorId Apify actor ID
     *
     * @return ApifyInputTemplate|null Template if found, null otherwise
     */
    public function getTemplateForActor(string $apifyActorId): ?ApifyInputTemplate
    {
        if (!isset($this->templatesConfig[$apifyActorId])) {
            return null;
        }

        return $this->buildTemplate($apifyActorId, $this->templatesConfig[$apifyActorId]);
    }

    /**
     * Get template for a specific SourceType.
     *
     * @return ApifyInputTemplate|null Template if found, null otherwise
     */
    public function getTemplateForSourceType(SourceType $sourceType): ?ApifyInputTemplate
    {
        // Iterate through all templates and find one that supports this source type
        foreach ($this->templatesConfig as $apifyActorId => $config) {
            /** @var array<string> $sourceTypes */
            $sourceTypes = $config['source_types'] ?? [];

            // Check if this template supports the source type
            foreach ($sourceTypes as $supportedType) {
                if ($supportedType === $sourceType->value) {
                    return $this->buildTemplate($apifyActorId, $config);
                }
            }
        }

        return null;
    }

    /**
     * Build an ApifyInputTemplate from config.
     *
     * @param string               $apifyActorId Apify actor ID
     * @param array<string, mixed> $config       Template config from YAML
     */
    private function buildTemplate(string $apifyActorId, array $config): ApifyInputTemplate
    {
        try {
            Assert::keyExists(
                $config,
                'defaults',
                \sprintf('Template for Apify actor "%s" must define "defaults"', $apifyActorId)
            );
            Assert::isArray(
                $config['defaults'],
                \sprintf('Template for Apify actor "%s" "defaults" must be an array', $apifyActorId)
            );
        } catch (\InvalidArgumentException $e) {
            throw ApifyConfigurationException::invalidTemplate($e->getMessage());
        }

        /** @var array<string, mixed> $defaults */
        $defaults = $config['defaults'];

        /** @var array<string> $requiredFields */
        $requiredFields = $config['required_fields'] ?? [];

        /** @var array<string, string>|null */
        $variableDefinitions = $config['variable_definitions'] ?? null;

        $rawCharge = $config['max_total_charge_usd'] ?? null;
        $maxTotalChargeUsd = \is_string($rawCharge) ? $rawCharge : null;

        return new ApifyInputTemplate(
            apifyActorId: $apifyActorId,
            defaults: $defaults,
            requiredFields: $requiredFields,
            variableDefinitions: $variableDefinitions,
            maxTotalChargeUsd: $maxTotalChargeUsd,
        );
    }
}
