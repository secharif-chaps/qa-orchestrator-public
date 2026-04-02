<?php

declare(strict_types=1);

namespace App\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;
use Psr\Log\LoggerInterface;
use TextCat;

/**
 * Language detector implementation using Wikimedia TextCat library.
 *
 * Uses n-gram based statistical language detection with a confidence threshold of 0.5.
 * The TextCat library returns distance scores (lower is better); if a language is returned,
 * we consider it confident (>=0.5), otherwise we return default with 0.0 confidence.
 */
class WikimediaTextCatLanguageDetector implements LanguageDetectorInterface
{
    private const float CONFIDENCE_WHEN_DETECTED = 0.8;
    private const int MINIMUM_TEXT_LENGTH = 10;
    private \TextCat $textCat;

    public function __construct(
        private readonly string $defaultLanguage = LanguageDetectorInterface::DEFAULT_LANGUAGE,
        private readonly float $defaultConfidence = LanguageDetectorInterface::DEFAULT_CONFIDENCE,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->textCat = new \TextCat();
    }

    public function detect(string $text): DetectedLanguage
    {
        $textLength = \strlen($text);

        // Handle very short text
        if ($textLength < self::MINIMUM_TEXT_LENGTH) {
            $this->logger?->info('Wikimedia TextCat language detected', [
                'text_length' => $textLength,
                'detected_language' => $this->defaultLanguage,
                'confidence' => $this->defaultConfidence,
                'status' => 'Input is too short',
            ]);

            return new DetectedLanguage($this->defaultLanguage, $this->defaultConfidence);
        }

        // Classify the text using TextCat
        $results = $this->textCat->classify($text);
        $status = $this->textCat->getResultStatus();

        // If no results or error status, return default
        if (empty($results) || '' !== $status) {
            $this->logger?->info('Wikimedia TextCat language detected', [
                'text_length' => $textLength,
                'detected_language' => $this->defaultLanguage,
                'confidence' => $this->defaultConfidence,
                'status' => $status,
            ]);

            return new DetectedLanguage($this->defaultLanguage, $this->defaultConfidence);
        }

        // Get the top result (first result in array)
        $detectedLanguage = array_key_first($results);
        $distanceScore = $results[$detectedLanguage];

        // TextCat returns distance scores - if it returned a result, it's confident
        // We use a fixed confidence of 0.8 when a language is detected
        $confidence = self::CONFIDENCE_WHEN_DETECTED;

        $this->logger?->info('Wikimedia TextCat language detected', [
            'text_length' => $textLength,
            'detected_language' => $detectedLanguage,
            'confidence' => $confidence,
            'distance_score' => $distanceScore,
            'status' => $status,
        ]);

        return new DetectedLanguage($detectedLanguage, $confidence);
    }
}
