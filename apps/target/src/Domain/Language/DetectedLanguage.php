<?php

declare(strict_types=1);

namespace App\Domain\Language;

/**
 * Value object representing a detected language with its confidence score.
 *
 * This immutable object contains the detected language code (ISO 639-1 format)
 * and the confidence score of the detection (0.0 to 1.0).
 */
readonly class DetectedLanguage
{
    /**
     * @param ?string $languageCode The detected language code (ISO 639-1 format, e.g., 'en', 'fr', 'de', 'es')
     * @param float   $confidence   The confidence score of the detection (0.0 to 1.0)
     */
    public function __construct(
        public ?string $languageCode,
        public float $confidence,
    ) {
        if (null !== $languageCode && !$this->isValidIso6391Code($languageCode)) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid ISO 639-1 language code: "%s". Expected 2 lowercase letters.',
                $languageCode
            ));
        }

        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new \InvalidArgumentException(\sprintf(
                'Confidence must be between 0.0 and 1.0, got %s',
                $confidence
            ));
        }
    }

    /**
     * Validate ISO 639-1 language code format.
     *
     * ISO 639-1 codes are 2-letter lowercase codes (e.g., 'en', 'fr', 'de').
     */
    private function isValidIso6391Code(string $code): bool
    {
        return 2 === \strlen($code) && ctype_lower($code) && ctype_alpha($code);
    }
}
