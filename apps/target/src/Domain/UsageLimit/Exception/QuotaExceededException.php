<?php

declare(strict_types=1);

namespace App\Domain\UsageLimit\Exception;

use App\Domain\Shared\DomainException;
use App\Domain\UsageLimit\QuotaType;

class QuotaExceededException extends DomainException
{
    public function __construct(
        private readonly QuotaType $quotaType,
        private readonly ?int $limit,
        private readonly ?int $current,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Creates a QuotaExceededException for the given quota type.
     *
     * @param QuotaType $quotaType The type of quota that was exceeded
     * @param int|null  $limit     The quota limit
     * @param int|null  $current   The current value
     */
    public static function create(QuotaType $quotaType, ?int $limit, ?int $current): self
    {
        $message = $quotaType->getTranslationKey();

        return new self($quotaType, $limit, $current, $message);
    }

    public static function forWatchFileOwner(int $current, int $limit): self
    {
        return self::create(QuotaType::WATCHFILE_MAX_OWNED_NON_ARCHIVED, $limit, $current);
    }

    public static function forWatchFileActive(int $current, int $limit): self
    {
        return self::create(QuotaType::WATCHFILE_MAX_ACTIVE_PER_USER, $limit, $current);
    }

    public static function forActiveSourcesInWatchFile(int $current, int $limit): self
    {
        return self::create(QuotaType::SOURCE_MAX_ACTIVE_PER_WATCHFILE, $limit, $current);
    }

    public static function forSourceCreation(int $current, int $limit): self
    {
        return self::create(QuotaType::SOURCE_MAX_PER_WATCHFILE, $limit, $current);
    }

    public static function forActorCreation(int $current, int $limit): self
    {
        return self::create(QuotaType::ACTOR_MAX_PER_WATCHFILE, $limit, $current);
    }

    public static function forDocumentQuota(int $quota): self
    {
        return self::create(QuotaType::DOCUMENT_MAX_PER_WATCHFILE, $quota, null);
    }

    public static function forDocumentsInWatchFile(int $current, int $limit): self
    {
        return new self(
            QuotaType::DOCUMENT_MAX_PER_WATCHFILE,
            $limit,
            $current,
            \sprintf(
                'A watchfile cannot contain more than %d documents. Current documents: %d.',
                $limit,
                $current,
            ),
        );
    }

    public function quotaType(): QuotaType
    {
        return $this->quotaType;
    }

    public function limit(): ?int
    {
        return $this->limit;
    }

    public function current(): ?int
    {
        return $this->current;
    }

    /**
     * Returns the translation key for this quota exception.
     *
     * @return string The translation key
     */
    public function getTranslationKey(): string
    {
        return $this->quotaType->getTranslationKey();
    }

    /**
     * Returns the parameters for the translation.
     *
     * @return array<string, int|string> The translation parameters
     */
    public function getTranslationParameters(): array
    {
        $params = [];
        if (null !== $this->limit) {
            $params['limit'] = $this->limit;
        }
        if (null !== $this->current) {
            $params['current'] = $this->current;
        }

        return $params;
    }
}
