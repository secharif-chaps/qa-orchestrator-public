<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Logo;

use App\Domain\Logo\LogoNotFoundException;
use App\Infrastructure\Logo\LogoDevGateway;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class LogoDevGatewayTest extends TestCase
{
    private HttpClientInterface&Stub $httpClient;
    private NullLogger $logger;
    private LogoDevGateway $gateway;

    protected function setUp(): void
    {
        $this->httpClient = $this->createStub(HttpClientInterface::class);
        $this->logger = new NullLogger();
        $this->buildGateway();
    }

    private function buildGateway(): void
    {
        $this->gateway = new LogoDevGateway('test-api-key', $this->httpClient, $this->logger);
    }

    public function testGetLogoSuccess(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClientMock;
        $this->buildGateway();
        $domain = 'example.com';
        $content = $this->createValidPngData();

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($content);

        $httpClientMock->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                'https://img.logo.dev/example.com',
                [
                    'timeout' => 10,
                    'max_duration' => 10,
                    'query' => [
                        'token' => 'test-api-key',
                        'format' => 'png',
                        'retina' => true,
                        'size' => '300',
                    ],
                ]
            )
            ->willReturn($response);

        $logo = $this->gateway->getLogo($domain);

        $this->assertEquals($content, $logo->content);
        $this->assertEquals('image/png', $logo->mimeType);
        $this->assertEquals($domain, $logo->domain);
    }

    public function testGetLogoThrowsExceptionOnHttpError(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClientMock;
        $this->buildGateway();
        $domain = 'example.com';

        $httpClientMock->expects($this->once())
            ->method('request')
            ->willThrowException(new \Exception('HTTP error'));

        $this->expectException(LogoNotFoundException::class);
        $this->gateway->getLogo($domain);
    }

    public function testGetLogoThrowsExceptionOnOversizedResponse(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClientMock;
        $this->buildGateway();
        $domain = 'example.com';
        $oversizedContent = str_repeat('x', 6 * 1024 * 1024); // 6MB

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($oversizedContent);

        $httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(LogoNotFoundException::class);
        $this->gateway->getLogo($domain);
    }

    public function testGetLogoThrowsExceptionOnEmptyResponse(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClientMock;
        $this->buildGateway();
        $domain = 'example.com';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn('');

        $httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(LogoNotFoundException::class);
        $this->gateway->getLogo($domain);
    }

    public function testGetLogoThrowsExceptionOnInvalidImageContent(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClientMock;
        $this->buildGateway();
        $domain = 'example.com';
        $invalidContent = 'not-an-image';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($invalidContent);

        $httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $this->expectException(LogoNotFoundException::class);
        $this->gateway->getLogo($domain);
    }

    public function testSupportsReturnsTrueForNonEmptyDomain(): void
    {
        $this->assertTrue($this->gateway->supports('example.com'));
        $this->assertTrue($this->gateway->supports('sub.domain.co.uk'));
    }

    public function testSupportsReturnsFalseForEmptyDomain(): void
    {
        $this->assertFalse($this->gateway->supports(''));
    }

    public function testGetLogoHandlesSvgContent(): void
    {
        $httpClientMock = $this->createMock(HttpClientInterface::class);
        $this->httpClient = $httpClientMock;
        $this->buildGateway();
        $domain = 'example.com';
        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100"/></svg>';

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('getContent')
            ->willReturn($svgContent);

        $httpClientMock->expects($this->once())
            ->method('request')
            ->willReturn($response);

        $logo = $this->gateway->getLogo($domain);

        $this->assertEquals('image/svg+xml', $logo->mimeType);
        $this->assertEquals('<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg">
  <rect width="100" height="100"></rect>
</svg>
', $logo->content);
    }

    private function createValidPngData(): string
    {
        // Minimal valid PNG file header
        return "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x00\x01\x00\x00\x00\x00\x00\x00IEND\xaeB`\x82";
    }
}
