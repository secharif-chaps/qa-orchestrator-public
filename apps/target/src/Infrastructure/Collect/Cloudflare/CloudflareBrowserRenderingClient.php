<?php

declare(strict_types=1);

namespace App\Infrastructure\Collect\Cloudflare;

use App\Domain\Document\HtmlFetcherInterface;
use App\Domain\Document\HtmlFetchException;
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
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function fetch(string $url): string
    {
        if ('' === $this->accountId || '' === $this->apiToken) {
            throw HtmlFetchException::fetchFailed(
                $url,
                'Cloudflare Browser Rendering is not configured. Set CLOUDFLARE_ACCOUNT_ID and CLOUDFLARE_BR_API_TOKEN in your .env file (credentials available in Passbolt).',
            );
        }

        $endpoint = \sprintf('%s/%s/browser-rendering/content', self::BASE_URL, $this->accountId);

        $this->logger?->info('Fetching URL via Cloudflare Browser Rendering', [
            'url' => $url,
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
                'timeout' => 30,
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
                'url' => $url,
                'provider_name' => 'cloudflare',
                'content_length' => \strlen($html),
            ]);

            return $html;
        } catch (CloudflareFetchException $e) {
            throw new HtmlFetchException($e->getMessage(), 0, $e);
        } catch (\Throwable $e) {
            $this->logger?->error('Cloudflare Browser Rendering fetch failed', [
                'url' => $url,
                'provider_name' => 'cloudflare',
                'error' => $e->getMessage(),
            ]);

            throw HtmlFetchException::fetchFailed($url, $e->getMessage());
        }
    }
}
