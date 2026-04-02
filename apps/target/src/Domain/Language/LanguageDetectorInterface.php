<?php

declare(strict_types=1);

namespace App\Domain\Language;

interface LanguageDetectorInterface
{
    public const float CONFIDENCE_THRESHOLD = 0.5;
    public const string DEFAULT_LANGUAGE = 'en';
    public const float DEFAULT_CONFIDENCE = 0.0;

    /**
     * Detect the language of the given text.
     *
     * @param string $text The text to analyze for language detection
     *
     * @return DetectedLanguage The detected language with confidence score
     */
    public function detect(string $text): DetectedLanguage;
}
