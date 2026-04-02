<?php

declare(strict_types=1);

namespace App\Tests\Units\Application\Logo;

use App\Application\Logo\GetLogoAction;
use App\Application\Logo\GetLogoHandler;
use App\Domain\Logo\Logo;
use App\Domain\Logo\LogoGatewayInterface;
use App\Domain\Logo\LogoNotFoundException;
use App\Tests\Utils\MockHelpersTrait;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class GetLogoHandlerTest extends TestCase
{
    use MockHelpersTrait;
    private TagAwareCacheInterface&Stub $cache;
    private NullLogger $logger;
    private ItemInterface&Stub $cacheItem;

    protected function setUp(): void
    {
        $this->cache = $this->createStub(TagAwareCacheInterface::class);
        $this->logger = new NullLogger();
        $this->cacheItem = $this->createStub(ItemInterface::class);
        $this->cacheItem->method('expiresAt')
            ->willReturnSelf();
    }

    public function testSuccessfulFetchFromFirstGateway(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $primaryGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);
        $secondaryGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);
        $expectedContent = $this->createValidPngData();
        $expectedLogo = new Logo($expectedContent, 'image/png', $domain);

        $primaryGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);

        $primaryGateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willReturn($expectedLogo);

        $secondaryGateway->expects($this->never())
            ->method('getLogo');

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturnCallback(fn ($key, $callback) => $callback($this->cacheItem));

        $handler = new GetLogoHandler([$primaryGateway, $secondaryGateway], $this->cache, $this->logger);
        $result = $handler($action);

        $this->assertEquals($expectedContent, $result);
    }

    public function testFallbackToSecondGatewayWhenFirstFails(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $primaryGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);
        $secondaryGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);
        $expectedContent = $this->createValidPngData();
        $expectedLogo = new Logo($expectedContent, 'image/png', $domain);

        $primaryGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);
        $primaryGateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willThrowException(new LogoNotFoundException($domain));

        $secondaryGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);
        $secondaryGateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willReturn($expectedLogo);

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturnCallback(fn ($key, $callback) => $callback($this->cacheItem));

        $handler = new GetLogoHandler([$primaryGateway, $secondaryGateway], $this->cache, $this->logger);
        $result = $handler($action);

        $this->assertEquals($expectedContent, $result);
    }

    public function testThrowsExceptionWhenAllGatewaysFail(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $primaryGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);
        $secondaryGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);

        $primaryGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);
        $primaryGateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willThrowException(new LogoNotFoundException($domain));

        $secondaryGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);
        $secondaryGateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willThrowException(new LogoNotFoundException($domain));

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturnCallback(fn ($key, $callback) => $callback($this->cacheItem));

        $handler = new GetLogoHandler([$primaryGateway, $secondaryGateway], $this->cache, $this->logger);

        $this->expectException(LogoNotFoundException::class);

        $handler($action);
    }

    public function testSkipsUnsupportedGateways(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $unsupportedGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);
        $supportedGateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);
        $expectedContent = $this->createValidPngData();
        $expectedLogo = new Logo($expectedContent, 'image/png', $domain);

        $unsupportedGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(false);

        $unsupportedGateway->expects($this->never())
            ->method('getLogo');

        $supportedGateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);

        $supportedGateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willReturn($expectedLogo);

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturnCallback(fn ($key, $callback) => $callback($this->cacheItem));

        $handler = new GetLogoHandler([$unsupportedGateway, $supportedGateway], $this->cache, $this->logger);
        $result = $handler($action);

        $this->assertEquals($expectedContent, $result);
    }

    public function testUsesCache(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $gateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);
        $cachedContent = $this->createValidPngData();

        $gateway->expects($this->never())
            ->method('getLogo');

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturn($cachedContent);

        $handler = new GetLogoHandler([$gateway], $this->cache, $this->logger);
        $result = $handler($action);

        $this->assertEquals($cachedContent, $result);
    }

    public function testCacheStoresResult(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $gateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);
        $expectedContent = $this->createValidPngData();
        $expectedLogo = new Logo($expectedContent, 'image/png', $domain);

        $gateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);
        $gateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willReturn($expectedLogo);

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturnCallback(function ($key, $callback) {
                return $callback($this->cacheItem);
            });

        $handler = new GetLogoHandler([$gateway], $this->cache, $this->logger);
        $result = $handler($action);

        $this->assertEquals($expectedContent, $result);
    }

    public function testCacheKeyGeneration(): void
    {
        $cache = $this->createMockWithExpectations(TagAwareCacheInterface::class);
        $this->cache = $cache;
        $gateway = $this->createMockWithExpectations(LogoGatewayInterface::class);

        $domain = 'chapsvision.com';
        $action = new GetLogoAction($domain);
        $expectedContent = $this->createValidPngData();
        $expectedLogo = new Logo($expectedContent, 'image/png', $domain);

        $gateway->expects($this->once())
            ->method('supports')
            ->with($domain)
            ->willReturn(true);

        $gateway->expects($this->once())
            ->method('getLogo')
            ->with($domain)
            ->willReturn($expectedLogo);

        $cache->expects($this->once())
            ->method('get')
            ->with('logo_a74419aa702e2e47f589d56514c4e1e812684ed9')
            ->willReturnCallback(fn ($key, $callback) => $callback($this->cacheItem));

        $handler = new GetLogoHandler([$gateway], $this->cache, $this->logger);
        $handler($action);
    }

    private function createValidPngData(): string
    {
        // Minimal valid PNG file header
        return "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\x9cc\xf8\x0f\x00\x00\x01\x00\x01\x00\x00\x00\x00\x00\x00IEND\xaeB`\x82";
    }
}
