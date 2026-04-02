<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Language;

use App\Infrastructure\Language\PatrickschurLanguageDetector;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PatrickschurLanguageDetectorTest extends TestCase
{
    private PatrickschurLanguageDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new PatrickschurLanguageDetector();
    }

    public function testDetectEnglishTextReturnsDefaultDueToLowConfidence(): void
    {
        // Patrickschur library often returns <0.8 confidence for short English text
        $englishText = 'Hello, this is a test message in English. I would like to analyze some documents.';

        $detector = new PatrickschurLanguageDetector(confidenceThreshold: 0.8);
        $result = $detector->detect($englishText);

        // Library returns <0.8 confidence, so detector returns default
        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testDetectFrenchTextReturnsDefaultDueToLowConfidence(): void
    {
        $frenchText = 'Bonjour, ceci est un message de test en français. Je voudrais analyser quelques documents.';

        $detector = new PatrickschurLanguageDetector(confidenceThreshold: 0.8);
        $result = $detector->detect($frenchText);

        // Library returns <0.8 confidence for this French text, so detector returns default
        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testDetectEmptyTextReturnsEnglishWithZeroConfidence(): void
    {
        $emptyText = '';

        $result = $this->detector->detect($emptyText);

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testDetectVeryShortTextReturnsEnglishWithZeroConfidence(): void
    {
        $shortText = 'Hi';

        $result = $this->detector->detect($shortText);

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testDetectAmbiguousTextFallsBackToEnglish(): void
    {
        // Arrange - Numbers and symbols have low confidence
        $ambiguousText = '123456789';

        $result = $this->detector->detect($ambiguousText);

        $this->assertEquals('en', $result->languageCode);
        // Confidence will be 0.0 for ambiguous text (no results from detector)
        $this->assertEquals(0.0, $result->confidence);
    }

    // New tests for 0.5 threshold and any language support

    public function testDetectsGermanTextWithSufficientConfidence(): void
    {
        $germanText = 'Guten Tag, das ist eine Testnachricht auf Deutsch. Ich möchte einige Dokumente analysieren.';

        $result = $this->detector->detect($germanText);

        // This specific text happens to return >0.5 confidence from the library
        $this->assertEquals('de', $result->languageCode);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence);
    }

    public function testReturnsBelowThresholdResultAsDefault(): void
    {
        // Test with text that returns confidence below 0.5
        $spanishText = 'Hola, este es un mensaje de prueba en español. Me gustaría analizar algunos documentos.';

        $result = $this->detector->detect($spanishText);

        // Spanish text returns ~0.44 confidence from library, below 0.5 threshold
        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testThresholdConstantIsPointFive(): void
    {
        // Verify the threshold constant value through reflection
        $reflection = new \ReflectionClass(PatrickschurLanguageDetector::class);
        $constant = $reflection->getConstant('CONFIDENCE_THRESHOLD');

        $this->assertEquals(0.5, $constant, 'Threshold constant should be 0.5');
    }

    public function testLogsDetectionResults(): void
    {
        /** @var LoggerInterface&MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Language detected',
                $this->callback(function ($context) {
                    return isset($context['text_length'])
                        && isset($context['detected_language'])
                        && isset($context['confidence']);
                })
            );

        $detector = new PatrickschurLanguageDetector(logger: $logger);
        $detector->detect('Hello world, this is a test.');
    }
}
