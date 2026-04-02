<?php

declare(strict_types=1);

namespace App\Infrastructure\Logo;

class GoogleFaviconGateway extends AbstractHttpLogoGateway
{
    protected function buildUrl(string $domain): string
    {
        return 'https://www.google.com/s2/favicons';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getRequestOptions(string $domain): array
    {
        return [
            'query' => [
                'domain' => $domain,
                'sz' => '128',
            ],
        ];
    }
}
