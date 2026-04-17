<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Apify;

use App\Domain\Collect\Exception\CollectException;

/**
 * Value object encapsulating the compound Apify provider task ID.
 *
 * Apify API requires both actorId and runId in the URL path,
 * but ProviderGatewayInterface only passes a single string taskId.
 * This VO encodes/decodes the compound key "actorId:runId".
 */
readonly class ApifyRunReference
{
    public const string SEPARATOR = ':';

    public function __construct(
        public string $actorId,
        public string $runId,
    ) {
    }

    public static function fromProviderTaskId(string $providerTaskId): self
    {
        // Limit to 2 so any stray separator in the runId (unexpected in
        // practice but tolerated) doesn't break parsing. The actorId is
        // normalized upstream by ApifyCollectTaskMapper to never contain
        // the separator.
        $parts = explode(self::SEPARATOR, $providerTaskId, 2);

        if (2 !== \count($parts) || '' === $parts[0] || '' === $parts[1]) {
            throw new CollectException(\sprintf(
                'Invalid Apify provider task ID format: "%s". Expected "actorId:runId".',
                $providerTaskId,
            ));
        }

        return new self($parts[0], $parts[1]);
    }

    public function toProviderTaskId(): string
    {
        return $this->actorId . self::SEPARATOR . $this->runId;
    }

    /**
     * Returns the Apify API path segment for this run.
     */
    public function getRunPath(): string
    {
        return \sprintf('/v2/acts/%s/runs/%s', $this->actorId, $this->runId);
    }
}
