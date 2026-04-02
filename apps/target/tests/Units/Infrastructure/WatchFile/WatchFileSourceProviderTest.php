<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Infrastructure\WatchFile\WatchFileSourceProvider;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class WatchFileSourceProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private NullWatchFileGateway $watchFileGateway;
    private Security $security;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createStub(Security::class);
    }

    public function testProvideDelegatesToApiPlatformProviderAndChecksWatchFile(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [];
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $expectedResult = [
            new Source(
                'Test Source',
                TranslatedText::fromArray([
                    'fr' => 'desc',
                    'en' => 'desc',
                ]),
                SourceType::WEBSITE,
                'https://example.com',
                'example.com',
                TranslatedText::fromArray([
                    'fr' => 'rel',
                    'en' => 'rel',
                ]),
                $actor,
            ),
        ];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideThrowsWatchFileNotFoundExceptionIfWatchFileDoesNotExist(): void
    {
        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'non-existent-watchfile-id',
        ];
        $context = [];
        $apiPlatformProvider = $this->createStub(ProviderInterface::class);
        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);

        $this->expectException(\App\Domain\WatchFile\Exception\WatchFileNotFoundException::class);
        $provider->provide($operation, $uriVariables, $context);
    }

    public function testProvideThrowsAccessDeniedExceptionWhenUserNotGrantedAccess(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [];

        $apiPlatformProvider = $this->createStub(ProviderInterface::class);
        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);

        $this->expectException(AccessDeniedException::class);
        $provider->provide($operation, $uriVariables, $context);
    }

    public function testProvideWithActiveFilterReturnsOnlyActiveSources(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [
            'filters' => [
                'isActive' => 'true',
            ],
        ];

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $activeSource = new Source(
            'Active Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor,
        );
        $expectedResult = [$activeSource];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideWithTypeFilterReturnsFilteredSources(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [
            'filters' => [
                'type' => 'website,rss_feed',
            ],
        ];

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $websiteSource = new Source(
            'Website Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor,
        );
        $rssSource = new Source(
            'RSS Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::RSS_FEED,
            'https://rss.example.com',
            'rss.example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor,
        );
        $expectedResult = [$websiteSource, $rssSource];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideWithSortingReturnsOrderedSources(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [
            'filters' => [
                'order' => [
                    'name' => 'asc',
                ],
            ],
        ];

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $sourceA = new Source(
            'A Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://a.example.com',
            'a.example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor);
        $sourceB = new Source(
            'B Source',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://b.example.com',
            'b.example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor);
        $expectedResult = [$sourceA, $sourceB];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideWithSearchFilterReturnsMatchingSources(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [
            'filters' => [
                'search' => 'bitcoin',
            ],
        ];

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $bitcoinSource = new Source(
            'Bitcoin News',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://bitcoin.example.com',
            'bitcoin.example.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor,
        );
        $expectedResult = [$bitcoinSource];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideWithPaginationReturnsPaginatedResults(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $operation = $this->createStub(Operation::class);
        $uriVariables = [
            'id' => 'watch_file_id',
        ];
        $context = [
            'filters' => [
                'page' => 2,
                'limit' => 10,
            ],
        ];

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source1 = new Source(
            'Source 1',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://example1.com',
            'example1.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor,
        );
        $source2 = new Source(
            'Source 2',
            TranslatedText::fromArray([
                'fr' => 'desc',
                'en' => 'desc',
            ]),
            SourceType::WEBSITE,
            'https://example2.com',
            'example2.com',
            TranslatedText::fromArray([
                'fr' => 'rel',
                'en' => 'rel',
            ]),
            $actor
        );
        $expectedResult = [$source1, $source2];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }

    public function testProvideWithoutWatchFileIdSkipsSecurityCheck(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $operation = $this->createStub(Operation::class);
        $uriVariables = [];
        $context = [];
        $expectedResult = [
            new Source(
                'Test Source',
                TranslatedText::fromArray([
                    'fr' => 'desc',
                    'en' => 'desc',
                ]),
                SourceType::WEBSITE,
                'https://example.com',
                'example.com',
                TranslatedText::fromArray([
                    'fr' => 'rel',
                    'en' => 'rel',
                ]),
                null
            ),
        ];

        $apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $apiPlatformProvider->expects($this->once())
            ->method('provide')
            ->with($operation, $uriVariables, $context)
            ->willReturn($expectedResult);

        $securityMock->expects($this->never())
            ->method('isGranted');

        $provider = new WatchFileSourceProvider($apiPlatformProvider, $this->watchFileGateway, $this->security);
        $result = $provider->provide($operation, $uriVariables, $context);

        $this->assertSame($expectedResult, $result);
    }
}
