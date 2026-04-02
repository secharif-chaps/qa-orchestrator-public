<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;

/**
 * Null implementation of LanguageDetectorInterface for testing purposes.
 *
 * Returns a configurable DetectedLanguage result, defaulting to English with 1.0 confidence.
 */
class NullLanguageDetector implements LanguageDetectorInterface
{
    private DetectedLanguage $detectedLanguage;

    public function __construct(?DetectedLanguage $detectedLanguage = null)
    {
        $this->detectedLanguage = $detectedLanguage ?? new DetectedLanguage('en', 1.0);
    }

    public function detect(string $text): DetectedLanguage
    {
        return $this->detectedLanguage;
    }

    public function setDetectedLanguage(DetectedLanguage $detectedLanguage): void
    {
        $this->detectedLanguage = $detectedLanguage;
    }
}
