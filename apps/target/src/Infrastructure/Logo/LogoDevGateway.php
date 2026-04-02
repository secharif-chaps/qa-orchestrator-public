<?php

declare(strict_types=1);

namespace App\Infrastructure\Logo;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LogoDevGateway extends AbstractHttpLogoGateway
{
    public function __construct(
        private readonly string $apiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger,
    ) {
        parent::__construct($httpClient, $logger);
    }

    protected function buildUrl(string $domain): string
    {
        return \sprintf('https://img.logo.dev/%s', $domain);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRequestOptions(string $domain): array
    {
        return [
            'query' => [
                'token' => $this->apiKey,
                'format' => 'png',
                'retina' => true,
                'size' => '300',
            ],
        ];
    }
}
