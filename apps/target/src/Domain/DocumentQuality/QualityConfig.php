<?php

declare(strict_types=1);

namespace App\Domain\DocumentQuality;

readonly class QualityConfig
{
    /**
     * @param array<string>        $trustedDomains        Domains always scored high
     * @param array<string>        $blockedDomains        Domains immediately rejected
     * @param array<string, float> $signalWeightOverrides Custom weights per signal name
     */
    public function __construct(
        public float $autoAcceptThreshold = 0.7,
        public float $autoRejectThreshold = 0.2,
        public bool $showReviewQueue = true,
        public array $trustedDomains = [],
        public array $blockedDomains = [],
        public array $signalWeightOverrides = [],
    ) {
    }
}
