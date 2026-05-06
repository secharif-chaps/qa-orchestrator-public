<?php

declare(strict_types=1);

namespace App\Domain\WatchFile\Exception;

use App\Domain\Shared\DomainException;

class WatchFileActivationException extends DomainException
{
    private const string TRANSLATION_KEY_MISSING_ACTIVE_SOURCE = 'watchfile.activation.missing_active_source';
    private const string TRANSLATION_KEY_MISSING_REFERENCE_SUBJECT = 'watchfile.activation.missing_reference_subject';

    private function __construct(
        private readonly string $translationKey,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missingActiveSource(string $watchFileId): self
    {
        return new self(
            self::TRANSLATION_KEY_MISSING_ACTIVE_SOURCE,
            \sprintf('Cannot activate watchfile "%s": at least one active source is required.', $watchFileId)
        );
    }

    public static function missingReferenceSubject(string $watchFileId): self
    {
        return new self(
            self::TRANSLATION_KEY_MISSING_REFERENCE_SUBJECT,
            \sprintf('Cannot activate watchfile "%s": a reference subject is required.', $watchFileId)
        );
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}
