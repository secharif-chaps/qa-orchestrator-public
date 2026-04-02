<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;
use App\Infrastructure\Language\ChainOfResponsibilityLanguageDetector;
use PHPUnit\Framework\TestCase;

class ChainOfResponsibilityLanguageDetectorTest extends TestCase
{
    public function testFirstDetectorSuccessStopsChain(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('fr', 0.9));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->never())
            ->method('detect');

        $detector3 = $this->createMock(LanguageDetectorInterface::class);
        $detector3->expects($this->never())
            ->method('detect');

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2, $detector3]);
        $result = $chain->detect('Bonjour');

        $this->assertEquals('fr', $result->languageCode);
        $this->assertEquals(0.9, $result->confidence);
    }

    public function testChainContinuesOnLowConfidence(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.3));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('de', 0.8));

        $detector3 = $this->createMock(LanguageDetectorInterface::class);
        $detector3->expects($this->never())
            ->method('detect');

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2, $detector3]);
        $result = $chain->detect('Guten Tag');

        $this->assertEquals('de', $result->languageCode);
        $this->assertEquals(0.8, $result->confidence);
    }

    public function testAllDetectorsFailReturnsDefault(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.2));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.3));

        $detector3 = $this->createMock(LanguageDetectorInterface::class);
        $detector3->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.4));

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2, $detector3]);
        $result = $chain->detect('123');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testLogsFullChainPath(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.3));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('fr', 0.9));

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2]);
        $chain->detect('Bonjour');
    }

    public function testPerformanceLoggingRecordsTimestamps(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.9));

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1]);
        $chain->detect('Hello');
    }

    public function testDetectorExecutionOrder(): void
    {
        $executionOrder = [];

        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturnCallback(function () use (&$executionOrder) {
                $executionOrder[] = 'detector1';

                return new DetectedLanguage('en', 0.3);
            });

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturnCallback(function () use (&$executionOrder) {
                $executionOrder[] = 'detector2';

                return new DetectedLanguage('fr', 0.8);
            });

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2]);
        $chain->detect('test');

        $this->assertEquals(['detector1', 'detector2'], $executionOrder);
    }

    public function testExactlyPointFiveConfidenceStopsChain(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('es', 0.5));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->never())
            ->method('detect');

        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2]);
        $result = $chain->detect('Hola');

        $this->assertEquals('es', $result->languageCode);
        $this->assertEquals(0.5, $result->confidence);
    }
}
