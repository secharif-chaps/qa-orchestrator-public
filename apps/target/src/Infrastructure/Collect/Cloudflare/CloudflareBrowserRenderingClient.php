<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Cloudflare;

use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
use App\Domain\Url\Exception\UnsafeUrlException;
use App\Domain\Url\UrlSanitizerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class CloudflareBrowserRenderingClient implements HtmlFetcherInterface
{
    private const string BASE_URL = 'https://api.cloudflare.com/client/v4/accounts';

    public function __construct(
        #[Autowire('%env(string:CLOUDFLARE_ACCOUNT_ID)%')]
        private string $accountId,
        #[Autowire('%env(string:CLOUDFLARE_BR_API_TOKEN)%')]
        private string $apiToken,
        private HttpClientInterface $httpClient,
        private UrlSanitizerInterface $urlSanitizer,
        #[Autowire(
            '%env(default:default_cloudflare_connect_timeout:int:CLOUDFLARE_BROWSER_RENDER_CONNECT_TIMEOUT_SECONDS)%'
        )]
        private int $connectTimeoutSeconds = 5,
        #[Autowire('%env(default:default_cloudflare_request_timeout:int:CLOUDFLARE_BROWSER_RENDER_TIMEOUT_SECONDS)%')]
        private int $requestTimeoutSeconds = 30,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * `.env.example` ships these vars with the `changeme` placeholder so
     * `task doctor` flags them. Treat that value as "not configured" along
     * with the empty string.
     */
    private function isConfigured(): bool
    {
        return '' !== $this->accountId
            && 'changeme' !== $this->accountId
            && '' !== $this->apiToken
            && 'changeme' !== $this->apiToken;
    }

    public function fetch(string $url): string
    {
        if (!$this->isConfigured()) {
            throw HtmlFetchException::fetchFailed(
                $url,
                'Cloudflare Browser Rendering is not configured. Set CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_BR_API_TOKEN in your .env file (credentials available in Passbolt).',
            );
        }

        // SSRF guard: reject URLs targeting the local network or unsupported
        // schemes BEFORE we hand the value to the HTTP client. Cloudflare
        // would happily relay `http://10.0.0.1/admin` if asked.
        try {
            $this->urlSanitizer->assertSafePublicUrl($url);
        } catch (UnsafeUrlException $e) {
            throw HtmlFetchException::fetchFailed($url, $e->getMessage());
        }

        $endpoint = \sprintf('%s/%s/browser-rendering/content', self::BASE_URL, $this->accountId);
        $redactedUrl = $this->urlSanitizer->redactCredentials($url);

        $this->logger?->info('Fetching URL via Cloudflare Browser Rendering', [
            'url' => $redactedUrl,
            'provider_name' => 'cloudflare',
        ]);

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => \sprintf('Bearer %s', $this->apiToken),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'url' => $url,
                    'gotoOptions' => [
                        'waitUntil' => 'networkidle0',
                    ],
                ],
                'timeout' => $this->requestTimeoutSeconds,
                // Independent connect timeout: a TCP/TLS hang to the
                // Cloudflare endpoint should fail in seconds, not block
                // the worker (or the sync HTTP request) for the full
                // request budget. Tunable via env for the rare case
                // where Cloudflare is reachable only via a slow proxy.
                'max_duration' => $this->connectTimeoutSeconds + $this->requestTimeoutSeconds,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                throw CloudflareFetchException::fetchFailed($url, \sprintf('HTTP %d', $statusCode));
            }

            /** @var array{success: bool, result?: string, errors?: list<array{message: string}>} $data */
            $data = $response->toArray(false);

            if (!$data['success']) {
                $errorMessage = $data['errors'][0]['message'] ?? 'Unknown Cloudflare error';
                throw CloudflareFetchException::fetchFailed($url, $errorMessage);
            }

            $html = $data['result'] ?? '';
            if ('' === $html) {
                throw CloudflareFetchException::emptyResult($url);
            }

            $this->logger?->info('Successfully fetched URL via Cloudflare Browser Rendering', [
                'url' => $redactedUrl,
                'provider_name' => 'cloudflare',
                'content_length' => \strlen($html),
            ]);

            return $html;
        } catch (CloudflareFetchException $e) {
            throw new HtmlFetchException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->logger?->error('Cloudflare Browser Rendering fetch failed', [
                'url' => $redactedUrl,
                'provider_name' => 'cloudflare',
                'error' => $e->getMessage(),
            ]);

            throw HtmlFetchException::fetchFailed($url, $e->getMessage());
        }
    }
}
