<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Language;

use App\Infrastructure\Language\WikimediaTextCatLanguageDetector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WikimediaTextCatLanguageDetectorTest extends TestCase
{
    private WikimediaTextCatLanguageDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new WikimediaTextCatLanguageDetector();
    }

    public function testDetectsEnglishText(): void
    {
        $englishText = 'Hello, this is a test message in English. I would like to analyze some documents.';

        $result = $this->detector->detect($englishText);

        $this->assertEquals('en', $result->languageCode);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence);
    }

    public function testDetectsFrenchText(): void
    {
        $frenchText = 'Bonjour, ceci est un message de test en français. Je voudrais analyser quelques documents.';

        $result = $this->detector->detect($frenchText);

        $this->assertEquals('fr', $result->languageCode);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence);
    }

    public function testDetectsGermanText(): void
    {
        $germanText = 'Guten Tag, das ist eine Testnachricht auf Deutsch. Ich möchte einige Dokumente analysieren.';

        $result = $this->detector->detect($germanText);

        $this->assertEquals('de', $result->languageCode);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence);
    }

    public function testDetectsSpanishText(): void
    {
        $spanishText = 'Hola, este es un mensaje de prueba en español. Me gustaría analizar algunos documentos.';

        $result = $this->detector->detect($spanishText);

        $this->assertEquals('es', $result->languageCode);
        $this->assertGreaterThanOrEqual(0.5, $result->confidence);
    }

    public function testShortTextReturnsDefaultWithZeroConfidence(): void
    {
        $shortText = 'Hi';

        $result = $this->detector->detect($shortText);

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testEmptyTextReturnsDefaultWithZeroConfidence(): void
    {
        $emptyText = '';

        $result = $this->detector->detect($emptyText);

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testLogsDetectionResults(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'Wikimedia TextCat language detected',
                $this->callback(function ($context) {
                    return isset($context['text_length'])
                        && isset($context['detected_language'])
                        && isset($context['confidence'])
                        && isset($context['status']);
                })
            );

        $detector = new WikimediaTextCatLanguageDetector(logger: $logger);
        $detector->detect('Hello world, this is a test.');
    }
}
