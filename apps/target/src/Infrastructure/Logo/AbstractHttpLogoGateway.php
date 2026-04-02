<?php

declare(strict_types=1);

namespace App\Infrastructure\Logo;

use App\Domain\Logo\Logo;
use App\Domain\Logo\LogoGatewayInterface;
use App\Domain\Logo\LogoNotFoundException;
use enshrined\svgSanitize\Sanitizer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractHttpLogoGateway implements LogoGatewayInterface
{
    protected const REQUEST_TIMEOUT = 10;
    protected const MAX_LOGO_SIZE = 5 * 1024 * 1024; // 5MB

    public function __construct(
        protected readonly HttpClientInterface $httpClient,
        protected readonly LoggerInterface $logger,
    ) {
    }

    public function getLogo(string $domain): Logo
    {
        try {
            $response = $this->httpClient->request(
                'GET',
                $this->buildUrl($domain),
                array_merge(
                    [
                        'timeout' => static::REQUEST_TIMEOUT,
                        'max_duration' => static::REQUEST_TIMEOUT,
                    ],
                    $this->getRequestOptions($domain),
                )
            );

            $content = $response->getContent();
            $content = $this->validateResponse($content);

            $mimeType = $this->detectMimeType($content);

            $this->logger->info('Logo fetched successfully', [
                'domain' => $domain,
                'gateway' => static::class,
                'size' => \strlen($content),
                'mime_type' => $mimeType,
            ]);

            return new Logo($content, $mimeType, $domain);
        } catch (\Exception $e) {
            $this->logger->warning('Logo gateway failed', [
                'domain' => $domain,
                'gateway' => static::class,
                'error' => $e->getMessage(),
            ]);

            throw new LogoNotFoundException($domain, $e);
        }
    }

    public function supports(string $domain): bool
    {
        return !empty($domain);
    }

    abstract protected function buildUrl(string $domain): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function getRequestOptions(string $domain): array;

    private function validateResponse(string $content): string
    {
        if (\strlen($content) > static::MAX_LOGO_SIZE) {
            throw new \RuntimeException('Logo response too large');
        }

        if (empty($content)) {
            throw new \RuntimeException('Empty logo response');
        }

        if ($this->isSvg($content)) {
            $sanitizedSvg = new Sanitizer()
                ->sanitize($content);

            if (false === $sanitizedSvg) {
                throw new \RuntimeException('SVG sanitization failed');
            }

            return $sanitizedSvg;
        }

        // Basic validation for image content
        $imageInfo = @getimagesizefromstring($content);
        if (false === $imageInfo) {
            throw new \RuntimeException('Invalid image content');
        }

        return $content;
    }

    private function isSvg(string $content): bool
    {
        return str_contains($content, '<svg') && str_contains($content, '</svg>');
    }

    private function detectMimeType(string $content): string
    {
        if ($this->isSvg($content)) {
            return 'image/svg+xml';
        }

        $imageInfo = @getimagesizefromstring($content);

        return $imageInfo['mime'] ?? 'image/png';
    }
}
