<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GetLogoControllerTest extends AbstractApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $response = new MockResponse(
            $this->createValidPngData(),
            [
                'http_code' => 200,
                'response_headers' => [
                    'Content-Type' => 'image/png',
                ],
            ],
        );

        $client = new MockHttpClient([
            $response,
            function () {
                $this->fail('The HTTP client should not be called more than once in these tests.');
            },
        ]);

        // Replace the HTTP client service
        $container = $this->getContainer();
        $container->set('test.http_client', $client);
        $container->set(HttpClientInterface::class, $client);
    }

    public function testGetLogoReturnsImageWithCorrectHeaders(): void
    {
        $client = self::createClient();
        $response = $client->request('GET', '/api/logo/chapsvision.com');

        $this->assertResponseIsSuccessful();

        // Check content type is detected correctly
        $this->assertResponseHeaderSame('Content-Type', 'image/png');

        // Check cache headers
        $this->assertResponseHeaderSame('Cache-Control', 'max-age=2592000, public, s-maxage=2592000');

        // Check CORS headers
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost');
        $this->assertResponseHeaderSame('Access-Control-Allow-Methods', 'GET');
        $this->assertResponseHeaderSame('Access-Control-Allow-Headers', 'Content-Type');
        $this->assertResponseHeaderSame('Access-Control-Max-Age', '86400');

        // Check response has content
        $this->assertNotEmpty($response->getContent());
    }

    public function testGetLogoWithSameDomainOrigin(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/logo/chapsvision.com', [
            'headers' => [
                'Origin' => 'http://localhost',
                'Host' => 'localhost',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Access-Control-Allow-Origin', 'http://localhost');
    }

    public function testGetLogoWithInvalidDomainReturns400(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/logo/invalid-domain');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetLogoWithEmptyDomainReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/logo/');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetLogoWithPrivateDomainReturns400(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/logo/localhost');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetLogoWithPrivateIpReturns400(): void
    {
        $client = self::createClient();
        $client->request('GET', '/api/logo/192.168.1.1');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testGetLogoIsPublicEndpoint(): void
    {
        // Test without authentication - should work
        $client = self::createClient();
        $response = $client->request('GET', '/api/logo/chapsvision.com');

        // Should succeed, not fail due to authentication
        $statusCode = $response->getStatusCode();
        $this->assertNotEquals(Response::HTTP_UNAUTHORIZED, $statusCode);
        $this->assertNotEquals(Response::HTTP_FORBIDDEN, $statusCode);
    }

    public function testGetLogoReturnsConsistentResponseForSameDomain(): void
    {
        $client = self::createClient();

        // First request
        $response = $client->request('GET', '/api/logo/chapsvision.com');
        $this->assertResponseIsSuccessful();
        $firstResponse = $response->getContent();
        $firstContentType = $response->getHeaders()['Content-Type'] ?? 'unknown';
        $this->assertEquals($this->createValidPngData(), $firstResponse);

        // Second request should return same content (cached)
        $response = $client->request('GET', '/api/logo/chapsvision.com');
        $this->assertResponseIsSuccessful();
        $secondResponse = $response->getContent();
        $secondContentType = $response->getHeaders()['Content-Type'] ?? 'unknown';

        $this->assertEquals($this->createValidPngData(), $secondResponse);

        $this->assertEquals($firstResponse, $secondResponse);
        $this->assertEquals($firstContentType, $secondContentType);
    }

    public function testGetLogoWithSpecialCharactersInDomain(): void
    {
        $client = self::createClient();

        // Test domain with special characters that should be rejected
        $client->request('GET', '/api/logo/' . urlencode('test@chapsvision.com'));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createValidPngData(): string
    {
        // Minimal valid PNG file header
        return "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x00\x01\x00\x00\x00\x00\x00\x00IEND\xaeB`\x82";
    }
}
