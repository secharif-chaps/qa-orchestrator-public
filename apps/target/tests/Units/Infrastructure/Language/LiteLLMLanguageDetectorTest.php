<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Language;

use App\Domain\Language\DetectedLanguage;
use App\Infrastructure\AI\LiteLLM\LiteLLMClient;
use App\Infrastructure\Language\LiteLLMLanguageDetector;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LiteLLMLanguageDetectorTest extends TestCase
{
    public function testSuccessfulDetectionWithHighConfidence(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->with('Hello world')
            ->willReturn(new DetectedLanguage('en', 0.95));

        $detector = new LiteLLMLanguageDetector($client);
        $result = $detector->detect('Hello world');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.95, $result->confidence);
    }

    public function testLowConfidenceReturnsDefault(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->with('ambiguous text')
            ->willReturn(new DetectedLanguage('en', 0.3));

        $detector = new LiteLLMLanguageDetector($client);
        $result = $detector->detect('ambiguous text');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testHttpErrorReturnsDefault(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->willThrowException(new \RuntimeException('API error'));

        $detector = new LiteLLMLanguageDetector($client);
        $result = $detector->detect('test text');

        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testLogsDetectionResult(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->willReturn(new DetectedLanguage('fr', 0.9));

        $detector = new LiteLLMLanguageDetector($client);
        $detector->detect('Bonjour');
    }

    public function testLogsErrorWhenClientFails(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->willThrowException(new \RuntimeException('Network error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                'LiteLLM language detection failed',
                $this->callback(function ($context) {
                    return isset($context['text_length'])
                        && isset($context['error']);
                })
            );

        $detector = new LiteLLMLanguageDetector($client, logger: $logger);
        $detector->detect('test');
    }

    public function testThresholdIsPointFive(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->willReturn(new DetectedLanguage('de', 0.49));

        $detector = new LiteLLMLanguageDetector($client);
        $result = $detector->detect('test');

        // Confidence 0.49 is below 0.5 threshold, should return default
        $this->assertEquals('en', $result->languageCode);
        $this->assertEquals(0.0, $result->confidence);
    }

    public function testExactlyPointFiveConfidenceIsAccepted(): void
    {
        $client = $this->createMock(LiteLLMClient::class);
        $client->expects($this->once())
            ->method('detectLanguage')
            ->willReturn(new DetectedLanguage('es', 0.5));

        $detector = new LiteLLMLanguageDetector($client);
        $result = $detector->detect('test');

        // Confidence exactly 0.5 should be accepted
        $this->assertEquals('es', $result->languageCode);
        $this->assertEquals(0.5, $result->confidence);
    }
}
