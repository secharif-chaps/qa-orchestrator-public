<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\LiteLLM;

use App\Domain\AI\Prompt\DetectLanguagePrompt;
use App\Domain\Language\DetectedLanguage;
use App\Domain\Language\Exception\LanguageDetectionException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTTP client for LiteLLM API (OpenAI-compatible format).
 *
 * Handles language detection requests using the OpenAI chat completions API format.
 */
class LiteLLMClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly DetectLanguagePrompt $detectLanguagePrompt,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $timeout = 5,
    ) {
    }

    /**
     * Detect language of text using LiteLLM API.
     *
     * @param string $text The text to analyze
     */
    public function detectLanguage(string $text): DetectedLanguage
    {
        // WARNING: Never log $text directly - may contain sensitive user data (GDPR)
        $this->logger?->info('LiteLLM language detection request', [
            'text_length' => \strlen($text),
            'model' => $this->model,
        ]);

        try {
            // Generate prompts using the domain prompt system
            $prompts = ($this->detectLanguagePrompt)($text);
            $prompt = $prompts[0]; // Use the first (and only) prompt

            // Prepare messages for the API request
            $messages = [
                [
                    'role' => 'user',
                    'content' => $prompt->content,
                ],
            ];

            // Prepare request body
            $requestBody = [
                'model' => $this->model,
                'messages' => $messages,
            ];

            // Add response format if schema is defined
            if (null !== $prompt->expectedResponseFormat) {
                $requestBody['response_format'] = [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'language_detection_response',
                        'strict' => true,
                        'schema' => $prompt->expectedResponseFormat,
                    ],
                ];
            }

            $response = $this->httpClient->request('POST', $this->baseUrl . '/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestBody,
                'timeout' => $this->timeout,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw LanguageDetectionException::httpError($statusCode, $response->getContent(false));
            }

            $data = $response->toArray();
        } catch (LanguageDetectionException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw LanguageDetectionException::apiRequestFailed($e->getMessage(), $e);
        }

        // Parse the response
        if (!isset($data['choices'][0]['message']['content'])) {
            throw LanguageDetectionException::invalidResponseFormat('missing message content');
        }

        $content = $data['choices'][0]['message']['content'];

        $languageData = $this->parseAndValidateResponse($content);

        $detectedLanguage = new DetectedLanguage(
            (string) $languageData['language'],
            (float) $languageData['confidence']
        );

        $this->logger?->info('LiteLLM language detection response', [
            'language' => $detectedLanguage->languageCode,
            'confidence' => $detectedLanguage->confidence,
        ]);

        return $detectedLanguage;
    }

    /**
     * Parse and validate JSON response from LiteLLM API.
     *
     * @return array{language: string|int|float, confidence: int|float}
     */
    private function parseAndValidateResponse(string $content): array
    {
        $data = json_decode($content, true);

        if (\JSON_ERROR_NONE !== json_last_error()) {
            throw LanguageDetectionException::invalidJsonResponse('invalid JSON in message content');
        }

        if (!\is_array($data)) {
            throw LanguageDetectionException::invalidJsonResponse('response is not an array');
        }

        $this->validateResponseStructure($data);

        /** @var array{language: string|int|float, confidence: int|float} */
        return $data;
    }

    /**
     * Validate the structure of the language detection response.
     *
     * @param array<mixed> $data The decoded JSON response
     */
    private function validateResponseStructure(array $data): void
    {
        if (!isset($data['language'])) {
            throw LanguageDetectionException::invalidResponseFormat('missing language field');
        }

        if (!isset($data['confidence'])) {
            throw LanguageDetectionException::invalidResponseFormat('missing confidence field');
        }

        if (!\is_string($data['language']) && !is_numeric($data['language'])) {
            throw LanguageDetectionException::invalidResponseFormat('language must be a string or numeric');
        }

        if (!is_numeric($data['confidence'])) {
            throw LanguageDetectionException::invalidResponseFormat('confidence must be numeric');
        }
    }
}
