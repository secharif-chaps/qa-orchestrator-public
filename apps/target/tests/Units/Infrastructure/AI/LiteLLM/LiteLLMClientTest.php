<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\AI\LiteLLM;

use App\Domain\AI\Prompt\DetectLanguagePrompt;
use App\Domain\AI\Prompt\Prompt;
use App\Domain\Chat\MessageRole;
use App\Domain\Language\Exception\LanguageDetectionException;
use App\Infrastructure\AI\LiteLLM\LiteLLMClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Webmozart\Assert\Assert;

class LiteLLMClientTest extends TestCase
{
    private function createDetectLanguagePromptMock(): DetectLanguagePrompt
    {
        $prompt = new Prompt(
            name: 'detect_language',
            content: 'Detect the language of the following text. Return JSON format: {"language": "XX", "confidence": 0.0-1.0} where XX is ISO 639-1 code.',
            role: MessageRole::User,
            expectedResponseFormat: [
                'type' => 'OBJECT',
                'properties' => [
                    'language' => [
                        'type' => 'STRING',
                        'pattern' => '^[a-z]{2}$',
                    ],
                    'confidence' => [
                        'type' => 'NUMBER',
                        'minimum' => 0.0,
                        'maximum' => 1.0,
                    ],
                ],
                'required' => ['language', 'confidence'],
            ],
            expectedResponseMimeType: 'application/json'
        );

        $mock = $this->createStub(DetectLanguagePrompt::class);
        $mock->method('__invoke')
            ->willReturn([$prompt]);

        return $mock;
    }

    public function testSuccessfulLanguageDetection(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"language": "fr", "confidence": 0.95}',
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $result = $client->detectLanguage('Bonjour, comment allez-vous?');

        $this->assertEquals('fr', $result->languageCode);
        $this->assertEquals(0.95, $result->confidence);
    }

    public function testHttpRequestFormat(): void
    {
        $requestChecker = function ($method, $url, $options) {
            $this->assertEquals('POST', $method);
            $this->assertEquals('https://api.example.com/v1/chat/completions', $url);
            $this->assertArrayHasKey('headers', $options);
            $this->assertContains('Authorization: Bearer test-api-key', $options['headers']);
            $this->assertContains('Content-Type: application/json', $options['headers']);
            $this->assertArrayHasKey('body', $options);

            $body = json_decode($options['body'], true);
            Assert::isArray($body);
            $this->assertEquals('gpt-4o-mini', $body['model']);
            $this->assertArrayHasKey('messages', $body);

            $responseBody = json_encode([
                'choices' => [[
                    'message' => [
                        'content' => '{"language": "en", "confidence": 0.9}',
                    ],
                ]],
            ]);
            Assert::string($responseBody);

            return new MockResponse($responseBody);
        };

        $httpClient = new MockHttpClient($requestChecker);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $client->detectLanguage('Hello world');
    }

    public function testHandles4xxError(): void
    {
        $mockResponse = new MockResponse('Bad Request', [
            'http_code' => 400,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('Language detection API request failed with status 400');

        $client->detectLanguage('test');
    }

    public function testHandles5xxError(): void
    {
        $mockResponse = new MockResponse('Internal Server Error', [
            'http_code' => 500,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('Language detection API request failed with status 500');

        $client->detectLanguage('test');
    }

    public function testInvalidJsonResponse(): void
    {
        $mockResponse = new MockResponse('invalid json', [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('Language detection API request failed');

        $client->detectLanguage('test');
    }

    public function testMissingLanguageFieldInResponse(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"confidence": 0.9}',
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('Invalid language detection response format: missing language field');

        $client->detectLanguage('test');
    }

    public function testLogsRequestAndResponse(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"language": "en", "confidence": 0.9}',
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->exactly(2))
            ->method('info');

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock(),
            $logger
        );

        $client->detectLanguage('Hello world');
    }

    public function testHandlesNetworkTimeout(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Timeout during connection');
        });

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('API request failed');

        $client->detectLanguage('test');
    }

    public function testHandlesEmptyChoicesArray(): void
    {
        $responseBody = json_encode([
            'choices' => [],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('missing message content');

        $client->detectLanguage('test');
    }

    public function testHandlesInvalidConfidenceValue(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"language": "en", "confidence": "not-a-number"}',
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('confidence must be numeric');

        $client->detectLanguage('test');
    }

    public function testHandlesNumericLanguageCode(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"language": 42, "confidence": 0.9}',
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        // Should accept numeric and convert to string, but will fail ISO validation in DetectedLanguage
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ISO 639-1 language code');

        $client->detectLanguage('test');
    }

    public function testHandlesVeryLongResponseBody(): void
    {
        $longError = str_repeat('Error: Something went wrong. ', 100); // ~3000 chars
        $mockResponse = new MockResponse($longError, [
            'http_code' => 500,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);

        try {
            $client->detectLanguage('test');
        } catch (LanguageDetectionException $e) {
            // Verify message is truncated
            $this->assertLessThan(300, \strlen($e->getMessage()));
            throw $e;
        }
    }

    public function testLogsRequestWithoutSensitiveData(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('info')
            ->with(
                $this->logicalOr(
                    $this->identicalTo('LiteLLM language detection request'),
                    $this->identicalTo('LiteLLM language detection response')
                ),
                $this->callback(function ($context) {
                    // Ensure we don't log the actual text content
                    return !isset($context['text']);
                })
            );

        $responseBody = json_encode([
            'choices' => [[
                'message' => [
                    'content' => '{"language": "en", "confidence": 0.9}',
                ],
            ]],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock(),
            $logger
        );

        $client->detectLanguage('Sensitive user content here');
    }

    public function testHandlesMalformedJsonInContent(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '{"language": "en", "confidence": 0.9', // Missing closing brace
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('invalid JSON in message content');

        $client->detectLanguage('test');
    }

    public function testHandlesNonObjectJsonResponse(): void
    {
        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => '["en", 0.9]', // Array instead of object
                    ],
                ],
            ],
        ]);
        Assert::string($responseBody);

        $mockResponse = new MockResponse($responseBody, [
            'http_code' => 200,
        ]);
        $httpClient = new MockHttpClient($mockResponse);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock()
        );

        $this->expectException(LanguageDetectionException::class);
        $this->expectExceptionMessage('missing language field');

        $client->detectLanguage('test');
    }

    public function testCustomTimeoutIsUsed(): void
    {
        $requestChecker = function ($method, $url, $options) {
            $this->assertArrayHasKey('timeout', $options);
            $this->assertEquals(15, $options['timeout']);

            $responseBody = json_encode([
                'choices' => [[
                    'message' => [
                        'content' => '{"language": "en", "confidence": 0.9}',
                    ],
                ]],
            ]);
            Assert::string($responseBody);

            return new MockResponse($responseBody);
        };

        $httpClient = new MockHttpClient($requestChecker);

        $client = new LiteLLMClient(
            $httpClient,
            'https://api.example.com',
            'test-api-key',
            'gpt-4o-mini',
            $this->createDetectLanguagePromptMock(),
            null,
            15 // Custom timeout
        );

        $client->detectLanguage('test');
    }
}
