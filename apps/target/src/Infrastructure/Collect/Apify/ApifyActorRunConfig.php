<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

/**
 * Value object representing the configuration for an Apify actor run.
 */
readonly class ApifyActorRunConfig
{
    /**
     * @param array<string, mixed> $input       The actor input payload (sent as JSON body)
     * @param array<string, mixed> $queryParams Query parameters (webhooks, maxTotalChargeUsd, etc.)
     */
    public function __construct(
        public string $actorId,
        public array $input,
        public array $queryParams,
    ) {
    }
}
