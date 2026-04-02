<?php

declare(strict_types=1);

namespace App\Infrastructure\Url;

use App\Domain\Url\FrontendUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Generates URLs for the frontend PWA application.
 *
 * Uses Symfony's router context to get the base URL (scheme + host)
 * and appends frontend-specific paths.
 */
class FrontendUrlGenerator implements FrontendUrlGeneratorInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * Generate the URL for a watch file detail page.
     */
    public function generateWatchFileUrl(string $watchFileId): string
    {
        return $this->generateUrl('/watch_files/' . $watchFileId);
    }

    /**
     * Generate a frontend URL with the given path.
     */
    public function generateUrl(string $path): string
    {
        $context = $this->urlGenerator->getContext();
        $scheme = $context->getScheme();
        $host = $context->getHost();

        return \sprintf('%s://%s%s', $scheme, $host, $path);
    }
}
