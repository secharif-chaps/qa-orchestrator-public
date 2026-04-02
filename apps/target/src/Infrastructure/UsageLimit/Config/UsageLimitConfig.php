<?php

declare(strict_types=1);

namespace App\Infrastructure\UsageLimit\Config;

use App\Domain\UsageLimit\Exception\InvalidQuotaConfigException;
use App\Domain\UsageLimit\QuotaLimit;
use App\Domain\UsageLimit\UsageLimitConfigInterface;

/**
 * Maps business quota identifiers from the Domain layer to YAML configuration paths.
 *
 * This class isolates the Domain from Infrastructure concerns by translating
 * domain-agnostic QuotaType identifiers into specific YAML configuration paths.
 * If the configuration structure changes, only this class needs to be updated.
 */
final class UsageLimitConfig implements UsageLimitConfigInterface
{
    /**
     * @param array<string, mixed> $limits Configuration array loaded from YAML
     */
    public function __construct(
        private readonly array $limits,
    ) {
    }

    /**
     * Maps QuotaType::WATCHFILE_MAX_OWNED_NON_ARCHIVED to YAML path:
     * usage_limits.watchfile.max_owned_non_archived
     */
    public function watchFileMaxOwnedNonArchived(): QuotaLimit
    {
        return $this->quotaLimit(
            'usage_limits.watchfile.max_owned_non_archived',
            ['watchfile', 'max_owned_non_archived']
        );
    }

    /**
     * Maps QuotaType::WATCHFILE_MAX_ACTIVE_PER_USER to YAML path:
     * usage_limits.watchfile.max_active_per_user
     */
    public function watchFileMaxActivePerUser(): QuotaLimit
    {
        return $this->quotaLimit('usage_limits.watchfile.max_active_per_user', ['watchfile', 'max_active_per_user']);
    }

    /**
     * Maps QuotaType::SOURCE_MAX_PER_WATCHFILE to YAML path:
     * usage_limits.source.max_per_watchfile
     */
    public function sourceMaxPerWatchFile(): QuotaLimit
    {
        return $this->quotaLimit('usage_limits.source.max_per_watchfile', ['source', 'max_per_watchfile']);
    }

    /**
     * Maps QuotaType::SOURCE_MAX_ACTIVE_PER_WATCHFILE to YAML path:
     * usage_limits.source.max_active_per_watchfile
     */
    public function sourceMaxActivePerWatchFile(): QuotaLimit
    {
        return $this->quotaLimit(
            'usage_limits.source.max_active_per_watchfile',
            ['source', 'max_active_per_watchfile']
        );
    }

    /**
     * Maps QuotaType::ACTOR_MAX_PER_WATCHFILE to YAML path:
     * usage_limits.actor.max_per_watchfile
     */
    public function actorMaxPerWatchFile(): QuotaLimit
    {
        return $this->quotaLimit('usage_limits.actor.max_per_watchfile', ['actor', 'max_per_watchfile']);
    }

    /**
     * Maps QuotaType::DOCUMENT_MAX_PER_WATCHFILE to YAML path:
     * usage_limits.document.max_per_watchfile
     */
    public function documentMaxPerWatchFile(): QuotaLimit
    {
        return $this->quotaLimit('usage_limits.document.max_per_watchfile', ['document', 'max_per_watchfile']);
    }

    /**
     * @param list<string> $pathSegments
     */
    private function quotaLimit(string $path, array $pathSegments): QuotaLimit
    {
        $value = $this->extractValue($path, $pathSegments);

        // Handle null (unlimited)
        if (null === $value) {
            return QuotaLimit::fromNullableInt(null);
        }

        // Handle simple format: just an integer
        if (\is_int($value)) {
            if ($value < 0) {
                throw InvalidQuotaConfigException::negativeValue($path, $value);
            }

            return QuotaLimit::fromNullableInt($value, inclusive: false);
        }

        // Handle complex format: array with 'limit' and optional 'inclusive'
        if (\is_array($value)) {
            if (!\array_key_exists('limit', $value)) {
                throw InvalidQuotaConfigException::invalidType($path, $value);
            }

            $limit = $value['limit'];
            if (null !== $limit && !\is_int($limit)) {
                throw InvalidQuotaConfigException::invalidType($path . '.limit', $limit);
            }

            if (\is_int($limit) && $limit < 0) {
                throw InvalidQuotaConfigException::negativeValue($path . '.limit', $limit);
            }

            $inclusive = $value['inclusive'] ?? false;
            if (!\is_bool($inclusive)) {
                throw InvalidQuotaConfigException::invalidType($path . '.inclusive', $inclusive);
            }

            return QuotaLimit::fromNullableInt($limit, $inclusive);
        }

        // Invalid type
        throw InvalidQuotaConfigException::invalidType($path, $value);
    }

    /**
     * @param list<string> $pathSegments
     */
    private function extractValue(string $path, array $pathSegments): mixed
    {
        $cursor = $this->limits;

        foreach ($pathSegments as $segment) {
            if (!\is_array($cursor)) {
                throw InvalidQuotaConfigException::invalidType($path, $cursor);
            }

            if (!\array_key_exists($segment, $cursor)) {
                throw InvalidQuotaConfigException::missing($path);
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
