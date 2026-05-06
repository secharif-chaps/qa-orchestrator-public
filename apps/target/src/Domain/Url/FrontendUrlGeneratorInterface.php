<?php

declare(strict_types=1);

namespace App\Domain\Url;

/**
 * Interface for generating URLs for the frontend PWA application.
 */
interface FrontendUrlGeneratorInterface
{
    /**
     * Generate the URL for a watchfile detail page.
     */
    public function generateWatchFileUrl(string $watchFileId): string;

    /**
     * Generate a frontend URL with the given path.
     */
    public function generateUrl(string $path): string;
}
