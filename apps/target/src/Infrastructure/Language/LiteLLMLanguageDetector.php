<?php

declare(strict_types=1);

namespace App\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;
use App\Infrastructure\AI\LiteLLM\LiteLLMClient;
use Psr\Log\LoggerInterface;

/**
 * Language detector implementation using LiteLLM AI API.
 *
 * Uses AI-based language detection as a fallback when statistical methods fail.
 * Requires confidence >= 0.5 to return detected language, otherwise returns default.
 */
class LiteLLMLanguageDetector implements LanguageDetectorInterface
{
    public function __construct(
        private readonly LiteLLMClient $client,
        private readonly float $confidenceThreshold = LanguageDetectorInterface::CONFIDENCE_THRESHOLD,
        private readonly string $defaultLanguage = LanguageDetectorInterface::DEFAULT_LANGUAGE,
        private readonly float $defaultConfidence = LanguageDetectorInterface::DEFAULT_CONFIDENCE,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function detect(string $text): DetectedLanguage
    {
        $textLength = \strlen($text);

        try {
            $detectedLanguage = $this->client->detectLanguage($text);

            // Check if confidence meets threshold
            if ($detectedLanguage->confidence < $this->confidenceThreshold) {
                $this->logger?->info('LiteLLM language detected', [
                    'text_length' => $textLength,
                    'detected_language' => $this->defaultLanguage,
                    'confidence' => self::DEFAULT_CONFIDENCE,
                    'ai_language' => $detectedLanguage->languageCode,
                    'ai_confidence' => $detectedLanguage->confidence,
                    'reason' => 'Below confidence threshold',
                ]);

                return new DetectedLanguage($this->defaultLanguage, $this->defaultConfidence);
            }

            $this->logger?->info('LiteLLM language detected', [
                'text_length' => $textLength,
                'detected_language' => $detectedLanguage->languageCode,
                'confidence' => $detectedLanguage->confidence,
            ]);

            return $detectedLanguage;
        } catch (\Throwable $e) {
            $this->logger?->error('LiteLLM language detection failed', [
                'text_length' => $textLength,
                'error' => $e->getMessage(),
            ]);

            return new DetectedLanguage($this->defaultLanguage, $this->defaultConfidence);
        }
    }
}
