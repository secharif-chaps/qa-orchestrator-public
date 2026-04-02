<?php

declare(strict_types=1);

namespace App\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;
use Psr\Log\LoggerInterface;

/**
 * Chain of Responsibility coordinator for language detection.
 *
 * Iterates through multiple language detectors in priority order, stopping when
 * confidence >= 0.5 is achieved. Falls back to default if all detectors fail.
 */
class ChainOfResponsibilityLanguageDetector implements LanguageDetectorInterface
{
    /**
     * @param iterable<LanguageDetectorInterface> $languageDetectors Tagged services in priority order
     */
    public function __construct(
        private readonly iterable $languageDetectors,
        private readonly float $confidenceThreshold = LanguageDetectorInterface::CONFIDENCE_THRESHOLD,
        private readonly string $defaultLanguage = LanguageDetectorInterface::DEFAULT_LANGUAGE,
        private readonly float $defaultConfidence = LanguageDetectorInterface::DEFAULT_CONFIDENCE,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function detect(string $text): DetectedLanguage
    {
        $textLength = \strlen($text);
        $chainStartTime = microtime(true);
        $attemptedDetectors = [];

        $this->logger?->info('Starting language detection chain', [
            'text_length' => $textLength,
            'threshold' => $this->confidenceThreshold,
        ]);

        foreach ($this->languageDetectors as $detector) {
            $detectorStartTime = microtime(true);
            $detectorClass = $detector::class;

            try {
                $result = $detector->detect($text);
                $detectorDuration = round((microtime(true) - $detectorStartTime) * 1000, 2);

                $attemptedDetectors[] = [
                    'detector' => $detectorClass,
                    'language' => $result->languageCode,
                    'confidence' => $result->confidence,
                    'duration_ms' => $detectorDuration,
                ];

                $this->logger?->info('Language detector executed', [
                    'detector' => $detectorClass,
                    'detected_language' => $result->languageCode,
                    'confidence' => $result->confidence,
                    'duration_ms' => $detectorDuration,
                    'threshold_met' => $result->confidence >= $this->confidenceThreshold,
                ]);

                // Stop chain if confidence threshold is met
                if ($result->confidence >= $this->confidenceThreshold) {
                    $totalDuration = round((microtime(true) - $chainStartTime) * 1000, 2);

                    $this->logger?->info('Language detection chain completed successfully', [
                        'final_language' => $result->languageCode,
                        'final_confidence' => $result->confidence,
                        'successful_detector' => $detectorClass,
                        'detectors_tried' => \count($attemptedDetectors),
                        'total_duration_ms' => $totalDuration,
                        'chain_path' => $attemptedDetectors,
                    ]);

                    return $result;
                }
            } catch (\Throwable $e) {
                $detectorDuration = round((microtime(true) - $detectorStartTime) * 1000, 2);

                $this->logger?->error('Language detector failed', [
                    'detector' => $detectorClass,
                    'error' => $e->getMessage(),
                    'duration_ms' => $detectorDuration,
                ]);

                $attemptedDetectors[] = [
                    'detector' => $detectorClass,
                    'error' => $e->getMessage(),
                    'duration_ms' => $detectorDuration,
                ];
            }
        }

        // All detectors failed to meet threshold
        $totalDuration = round((microtime(true) - $chainStartTime) * 1000, 2);

        $this->logger?->info('Language detection chain failed - all detectors below threshold', [
            'final_language' => $this->defaultLanguage,
            'final_confidence' => $this->defaultConfidence,
            'detectors_tried' => \count($attemptedDetectors),
            'total_duration_ms' => $totalDuration,
            'chain_path' => $attemptedDetectors,
        ]);

        return new DetectedLanguage($this->defaultLanguage, $this->defaultConfidence);
    }
}
