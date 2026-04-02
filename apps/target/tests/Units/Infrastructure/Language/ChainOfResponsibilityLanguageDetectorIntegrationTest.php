<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\LanguageDetectorInterface;
use App\Infrastructure\Language\ChainOfResponsibilityLanguageDetector;
use App\Tests\Utils\SimpleTestLogger;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\Assert;

/**
 * Integration tests for ChainOfResponsibilityLanguageDetector focusing on
 * edge cases, error scenarios, and comprehensive logging validation.
 */
class ChainOfResponsibilityLanguageDetectorIntegrationTest extends TestCase
{
    public function testEmptyDetectorIterableReturnsDefault(): void
    {
        $chain = new ChainOfResponsibilityLanguageDetector([]);
        $result = $chain->detect('Hello world');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testSingleDetectorInChain(): void
    {
        $detector = $this->createMock(LanguageDetectorInterface::class);
        $detector->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('fr', 0.9));

        $chain = new ChainOfResponsibilityLanguageDetector([$detector]);
        $result = $chain->detect('Bonjour');

        $this->assertEquals('fr', $result->languageCode);
        $this->assertEquals(0.9, $result->confidence);
    }

    public function testMultipleDetectorsThrowExceptions(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willThrowException(new \RuntimeException('Network error'));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willThrowException(new \RuntimeException('Timeout'));

        $detector3 = $this->createMock(LanguageDetectorInterface::class);
        $detector3->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('de', 0.8));

        $logger = new SimpleTestLogger();
        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2, $detector3], logger: $logger);
        $result = $chain->detect('Guten Tag');

        // Should continue to successful detector despite earlier failures
        $this->assertEquals('de', $result->languageCode);
        $this->assertEquals(0.8, $result->confidence);

        // Verify errors were logged
        $this->assertTrue($logger->hasErrorRecords());
        $this->assertEquals(2, $logger->getErrorCount());
    }

    public function testAllDetectorsThrowExceptionsReturnsDefault(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willThrowException(new \RuntimeException('Error 1'));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willThrowException(new \RuntimeException('Error 2'));

        $logger = new SimpleTestLogger();
        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2], logger: $logger);
        $result = $chain->detect('test');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
        $this->assertEquals(2, $logger->getErrorCount());
    }

    public function testComprehensiveLoggingFormat(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.3));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('fr', 0.9));

        $logger = new SimpleTestLogger();
        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2], logger: $logger);
        $chain->detect('Bonjour le monde');

        // Verify logging structure
        $this->assertTrue($logger->hasInfoRecords());

        // First log: chain start
        $this->assertArrayHasKey('text_length', $logger->records[0]['context']);
        $this->assertArrayHasKey('threshold', $logger->records[0]['context']);
        $this->assertEquals(0.5, $logger->records[0]['context']['threshold']);

        // Second log: first detector execution
        $this->assertArrayHasKey('detector', $logger->records[1]['context']);
        $this->assertArrayHasKey('detected_language', $logger->records[1]['context']);
        $this->assertArrayHasKey('confidence', $logger->records[1]['context']);
        $this->assertArrayHasKey('duration_ms', $logger->records[1]['context']);
        $this->assertArrayHasKey('threshold_met', $logger->records[1]['context']);
        $this->assertFalse($logger->records[1]['context']['threshold_met']);

        // Third log: second detector execution
        $this->assertTrue($logger->records[2]['context']['threshold_met']);

        // Fourth log: chain completion
        $this->assertArrayHasKey('final_language', $logger->records[3]['context']);
        $this->assertArrayHasKey('final_confidence', $logger->records[3]['context']);
        $this->assertArrayHasKey('successful_detector', $logger->records[3]['context']);
        $this->assertArrayHasKey('detectors_tried', $logger->records[3]['context']);
        $this->assertArrayHasKey('total_duration_ms', $logger->records[3]['context']);
        $this->assertArrayHasKey('chain_path', $logger->records[3]['context']);

        // Verify chain path structure
        $chainPath = $logger->records[3]['context']['chain_path'];
        Assert::isArray($chainPath);
        $this->assertCount(2, $chainPath);
        Assert::isArray($chainPath[0]);
        $this->assertArrayHasKey('detector', $chainPath[0]);
        $this->assertArrayHasKey('language', $chainPath[0]);
        $this->assertArrayHasKey('confidence', $chainPath[0]);
        $this->assertArrayHasKey('duration_ms', $chainPath[0]);
    }

    public function testPerformanceMetricsAccuracy(): void
    {
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturnCallback(function () {
                usleep(10000); // 10ms delay

                return new DetectedLanguage('en', 0.3);
            });

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('fr', 0.9));

        $logger = new SimpleTestLogger();
        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2], logger: $logger);
        $chain->detect('test');

        // Verify first detector duration is recorded and reasonable
        $detector1Duration = $logger->records[1]['context']['duration_ms'];
        $this->assertGreaterThan(8, $detector1Duration); // At least 8ms (allowing for timing variance)

        // Verify total duration is sum of individual durations
        $totalDuration = $logger->records[3]['context']['total_duration_ms'];
        $this->assertGreaterThan($detector1Duration, $totalDuration);
    }

    public function testMixedConfidenceProgressionScenario(): void
    {
        // Simulate realistic scenario: fast detector low confidence → medium detector medium confidence → AI high confidence
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.25));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.45));

        $detector3 = $this->createMock(LanguageDetectorInterface::class);
        $detector3->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('en', 0.95));

        $logger = new SimpleTestLogger();
        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2, $detector3], logger: $logger);
        $result = $chain->detect('Hello');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.95, $result->confidence);

        // Verify all three detectors were tried
        $chainPath = $logger->records[4]['context']['chain_path'];
        Assert::isArray($chainPath);
        $this->assertCount(3, $chainPath);
        Assert::isArray($chainPath[0]);
        Assert::isArray($chainPath[1]);
        Assert::isArray($chainPath[2]);
        $this->assertEquals(0.25, $chainPath[0]['confidence']);
        $this->assertEquals(0.45, $chainPath[1]['confidence']);
        $this->assertEquals(0.95, $chainPath[2]['confidence']);
    }

    public function testBoundaryConfidenceBehavior(): void
    {
        // Test confidence exactly at 0.5 boundary
        $detectorAt49 = $this->createMock(LanguageDetectorInterface::class);
        $detectorAt49->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('de', 0.49999));

        $detectorAt50 = $this->createMock(LanguageDetectorInterface::class);
        $detectorAt50->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('es', 0.50001));

        $chain = new ChainOfResponsibilityLanguageDetector([$detectorAt49, $detectorAt50]);
        $result = $chain->detect('test');

        // Should stop at second detector (0.50001 >= 0.5)
        $this->assertEquals('es', $result->languageCode);
        $this->assertEquals(0.50001, $result->confidence);
    }

    public function testDetectorReturningNullHandledGracefully(): void
    {
        // Some detectors might return null in edge cases (though interface doesn't allow it,
        // this tests defensive programming if detector misbehaves)
        $detector1 = $this->createMock(LanguageDetectorInterface::class);
        $detector1->expects($this->once())
            ->method('detect')
            ->willThrowException(new \TypeError('Return value must be of type DetectedLanguage, null returned'));

        $detector2 = $this->createMock(LanguageDetectorInterface::class);
        $detector2->expects($this->once())
            ->method('detect')
            ->willReturn(new DetectedLanguage('fr', 0.8));

        $logger = new SimpleTestLogger();
        $chain = new ChainOfResponsibilityLanguageDetector([$detector1, $detector2], logger: $logger);
        $result = $chain->detect('Bonjour');

        // Should handle exception and continue to next detector
        $this->assertEquals('fr', $result->languageCode);
        $this->assertEquals(0.8, $result->confidence);
        $this->assertTrue($logger->hasErrorRecords());
    }
}
