<?php

declare(strict_types=1);

namespace App\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;
use LanguageDetection\Language;
use Psr\Log\LoggerInterface;

/**
 * Language detector implementation using patrickschur/language-detection library.
 *
 * Detects languages from text with a confidence threshold of 0.5.
 * Falls back to English with 0.0 confidence for empty, very short, or ambiguous text.
 */
class PatrickschurLanguageDetector implements LanguageDetectorInterface
{
    private const int MINIMUM_TEXT_LENGTH = 3;
    private Language $languageDetector;

    public function __construct(
        private readonly float $confidenceThreshold = LanguageDetectorInterface::CONFIDENCE_THRESHOLD,
        private readonly string $defaultLanguage = LanguageDetectorInterface::DEFAULT_LANGUAGE,
        private readonly float $defaultConfidence = LanguageDetectorInterface::DEFAULT_CONFIDENCE,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->languageDetector = new Language();
    }

    public function detect(string $text): DetectedLanguage
    {
        $textLength = \strlen($text);

        // Handle empty or very short text
        if ($textLength < self::MINIMUM_TEXT_LENGTH) {
            return $this->createDefaultResult($textLength);
        }

        // Detect language using the library
        $results = $this->languageDetector->detect($text)
            ->bestResults()
            ->close();

        // Get the top result
        $detectedLanguage = $this->defaultLanguage;
        $confidence = $this->defaultConfidence;

        if (!empty($results)) {
            // Get the language with highest confidence
            $topLanguage = array_key_first($results);
            $topConfidence = $results[$topLanguage];

            // Only use detection if confidence is above threshold
            if ($topConfidence >= $this->confidenceThreshold) {
                $detectedLanguage = $topLanguage;
                $confidence = $topConfidence;
            }
        }

        $this->logger?->info('Language detected', [
            'text_length' => $textLength,
            'detected_language' => $detectedLanguage,
            'confidence' => $confidence,
        ]);

        return new DetectedLanguage($detectedLanguage, $confidence);
    }

    private function createDefaultResult(int $textLength): DetectedLanguage
    {
        $this->logger?->info('Language detected', [
            'text_length' => $textLength,
            'detected_language' => $this->defaultLanguage,
            'confidence' => $this->defaultConfidence,
        ]);

        return new DetectedLanguage($this->defaultLanguage, $this->defaultConfidence);
    }
}
