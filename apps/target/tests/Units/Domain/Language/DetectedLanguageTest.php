<?php

declare(strict_types=1);

namespace App\Tests\Units\Domain\Language;

use App\Domain\Language\DetectedLanguage;
use PHPUnit\Framework\TestCase;

class DetectedLanguageTest extends TestCase
{
    public function testCreateDetectedLanguageWithConfidenceBelowZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0, got -0.1');

        new DetectedLanguage('en', -0.1);
    }

    public function testCreateDetectedLanguageWithConfidenceAboveOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0, got 1.5');

        new DetectedLanguage('en', 1.5);
    }

    public function testCreateDetectedLanguageWithZeroConfidence(): void
    {
        $detectedLanguage = new DetectedLanguage('en', 0.0);

        $this->assertEquals('en', $detectedLanguage->languageCode);
        $this->assertEquals(0.0, $detectedLanguage->confidence);
    }

    public function testCreateDetectedLanguageWithMaxConfidence(): void
    {
        $detectedLanguage = new DetectedLanguage('fr', 1.0);

        $this->assertEquals('fr', $detectedLanguage->languageCode);
        $this->assertEquals(1.0, $detectedLanguage->confidence);
    }

    public function testRejectsInvalidIso6391CodeWithThreeLetters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        new DetectedLanguage('eng', 0.9); // 3 letters invalid
    }

    public function testRejectsInvalidIso6391CodeWithOneLetter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        new DetectedLanguage('e', 0.9); // 1 letter invalid
    }

    public function testRejectsUppercaseLanguageCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        new DetectedLanguage('EN', 0.9); // Uppercase invalid
    }

    public function testRejectsMixedCaseLanguageCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        new DetectedLanguage('En', 0.9); // Mixed case invalid
    }

    public function testRejectsLanguageCodeWithNumbers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        new DetectedLanguage('e1', 0.9); // With numbers invalid
    }

    public function testRejectsLanguageCodeWithSpecialCharacters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        new DetectedLanguage('e-', 0.9); // With special chars invalid
    }

    public function testAcceptsNullLanguageCode(): void
    {
        $detected = new DetectedLanguage(null, 0.0);

        $this->assertNull($detected->languageCode);
        $this->assertEquals(0.0, $detected->confidence);
    }

    public function testAcceptsValidIso6391Codes(): void
    {
        $validCodes = ['en', 'fr', 'de', 'es', 'it', 'ja', 'zh', 'ar', 'pt', 'ru'];

        foreach ($validCodes as $code) {
            $detected = new DetectedLanguage($code, 0.8);
            $this->assertEquals($code, $detected->languageCode);
        }
    }

    public function testConfidenceBoundaryValues(): void
    {
        // Test exact boundaries
        $min = new DetectedLanguage('en', 0.0);
        $max = new DetectedLanguage('en', 1.0);

        $this->assertEquals(0.0, $min->confidence);
        $this->assertEquals(1.0, $max->confidence);
    }

    public function testRejectsNegativeConfidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');

        new DetectedLanguage('en', -0.1);
    }

    public function testRejectsConfidenceAboveOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');

        new DetectedLanguage('en', 1.1);
    }

    public function testRejectsVeryNegativeConfidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');

        new DetectedLanguage('en', -999.9);
    }

    public function testRejectsVeryHighConfidence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Confidence must be between 0.0 and 1.0');

        new DetectedLanguage('en', 999.9);
    }

    public function testAcceptsDecimalConfidenceValues(): void
    {
        $values = [0.1, 0.25, 0.5, 0.75, 0.95, 0.999];

        foreach ($values as $value) {
            $detected = new DetectedLanguage('en', $value);
            $this->assertEquals($value, $detected->confidence);
        }
    }

    public function testReadonlyProperties(): void
    {
        $detected = new DetectedLanguage('fr', 0.85);

        // Verify it's a readonly class - properties should be immutable
        $this->assertEquals('fr', $detected->languageCode);
        $this->assertEquals(0.85, $detected->confidence);
    }
}
