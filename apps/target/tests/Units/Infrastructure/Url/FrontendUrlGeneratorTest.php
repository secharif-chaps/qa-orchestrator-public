<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Url;

use App\Infrastructure\Url\FrontendUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

class FrontendUrlGeneratorTest extends TestCase
{
    public function testGenerateWatchFileUrl(): void
    {
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('basil.local');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')
->willReturn($context);

        $frontendUrlGenerator = new FrontendUrlGenerator($urlGenerator);

        $url = $frontendUrlGenerator->generateWatchFileUrl('123e4567-e89b-12d3-a456-426614174000');

        $this->assertSame('https://basil.local/watch_files/123e4567-e89b-12d3-a456-426614174000', $url);
    }

    public function testGenerateWatchFileUrlWithHttpScheme(): void
    {
        $context = new RequestContext();
        $context->setScheme('http');
        $context->setHost('localhost');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')
->willReturn($context);

        $frontendUrlGenerator = new FrontendUrlGenerator($urlGenerator);

        $url = $frontendUrlGenerator->generateWatchFileUrl('abc-123');

        $this->assertSame('http://localhost/watch_files/abc-123', $url);
    }

    public function testGenerateUrl(): void
    {
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('example.com');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')
->willReturn($context);

        $frontendUrlGenerator = new FrontendUrlGenerator($urlGenerator);

        $url = $frontendUrlGenerator->generateUrl('/custom/path');

        $this->assertSame('https://example.com/custom/path', $url);
    }

    public function testGenerateUrlWithEmptyPath(): void
    {
        $context = new RequestContext();
        $context->setScheme('https');
        $context->setHost('basil.local');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('getContext')
->willReturn($context);

        $frontendUrlGenerator = new FrontendUrlGenerator($urlGenerator);

        $url = $frontendUrlGenerator->generateUrl('');

        $this->assertSame('https://basil.local', $url);
    }
}
