<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Operation;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\SourceTypesProvider;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Source\SourceTypesDto;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SourceTypesProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private NullSourceGateway $sourceGateway;
    private NullWatchFileGateway $watchFileGateway;
    private Security $security;
    private SourceTypesProvider $provider;
    private Operation $operation;

    protected function setUp(): void
    {
        $this->sourceGateway = new NullSourceGateway();
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->security = $this->createStub(Security::class);
        $this->operation = $this->createStub(Operation::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new SourceTypesProvider($this->sourceGateway, $this->watchFileGateway, $this->security);
    }

    private function setupWatchFile(string $watchFileId): WatchFile
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, $watchFileId);
        $this->watchFileGateway->save($watchFile);

        return $watchFile;
    }

    public function testProvideReturnsSourceTypesDto(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440000';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create sources with different types
        $source1 = new Source(
            'Website Source 1',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example1.com',
            'example1.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            'Website Source 2',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example2.com',
            'example2.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source2);

        $source3 = new Source(
            'RSS Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source3);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(2, $result->types); // Only unique types
        $this->assertArrayHasKey('website', $result->types);
        $this->assertArrayHasKey('rss_feed', $result->types);
        $this->assertEquals(2, $result->types['website']); // 2 website sources
        $this->assertEquals(1, $result->types['rss_feed']); // 1 rss_feed source
    }

    public function testProvideWithEmptyTypes(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440001';
        $this->setupWatchFile($watchFileId);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertEmpty($result->types);
    }

    public function testProvideThrowsExceptionWhenWatchFileIdMissing(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided in the URL');

        $this->provider->provide($this->operation, [], []);
    }

    public function testProvideThrowsExceptionWhenWatchFileIdIsNotString(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $this->provider->provide(
            $this->operation,
            [
                'watchFileId' => 123,
            ], // Integer instead of string
            []
        );
    }

    public function testProvideThrowsExceptionWhenWatchFileIdIsInvalidUuid(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a valid UUID');

        $this->provider->provide($this->operation, [
            'watchFileId' => 'invalid-uuid',
        ], []);
    }

    public function testProvideThrowsExceptionWhenAccessDenied(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440002';
        $this->setupWatchFile($watchFileId);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have access to this watch file.');

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], []);
    }

    public function testProvideWithActiveStatusFilter(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440003';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create active sources
        $activeSource1 = new Source(
            'Active Website Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example1.com',
            'example1.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $activeSource1->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($activeSource1);

        $activeSource2 = new Source(
            'Active RSS Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $activeSource2->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($activeSource2);

        // Create inactive source (should not be included)
        $inactiveSource = new Source(
            'Inactive Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example2.com',
            'example2.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $inactiveSource->setStatus(SourceStatus::INACTIVE);
        $this->sourceGateway->save($inactiveSource);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'status' => 'active',
            ],
        ]);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(2, $result->types);
        $this->assertArrayHasKey('website', $result->types);
        $this->assertArrayHasKey('rss_feed', $result->types);
        $this->assertEquals(1, $result->types['website']); // 1 active website
        $this->assertEquals(1, $result->types['rss_feed']); // 1 active rss_feed
    }

    public function testProvideWithInactiveStatusFilter(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440004';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create inactive sources
        $inactiveSource1 = new Source(
            'Inactive Website Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example1.com',
            'example1.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $inactiveSource1->setStatus(SourceStatus::INACTIVE);
        $this->sourceGateway->save($inactiveSource1);

        $inactiveSource2 = new Source(
            'Inactive Newsletter Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::NEWSLETTER,
            'https://example.com/newsletter',
            'example.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $inactiveSource2->setStatus(SourceStatus::INACTIVE);
        $this->sourceGateway->save($inactiveSource2);

        // Create active source (should not be included)
        $activeSource = new Source(
            'Active Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example2.com',
            'example2.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $activeSource->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($activeSource);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'status' => 'inactive',
            ],
        ]);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(2, $result->types);
        $this->assertArrayHasKey('website', $result->types);
        $this->assertArrayHasKey('newsletter', $result->types);
        $this->assertEquals(1, $result->types['website']); // 1 inactive website
        $this->assertEquals(1, $result->types['newsletter']); // 1 inactive newsletter
    }

    public function testProvideWithInactiveStatusFilterIncludesAutoDisabled(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440006';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create inactive source
        $inactiveSource = new Source(
            'Inactive Website Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example1.com',
            'example1.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $inactiveSource->setStatus(SourceStatus::INACTIVE);
        $this->sourceGateway->save($inactiveSource);

        // Create auto-disabled source (should be included when filtering by inactive)
        $autoDisabledSource = new Source(
            'Auto Disabled RSS Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $autoDisabledSource->setStatus(SourceStatus::AUTO_DISABLED);
        $this->sourceGateway->save($autoDisabledSource);

        // Create active source (should not be included)
        $activeSource = new Source(
            'Active Source',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::NEWSLETTER,
            'https://example2.com',
            'example2.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $activeSource->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($activeSource);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'status' => 'inactive',
            ],
        ]);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(2, $result->types);
        $this->assertArrayHasKey('website', $result->types);
        $this->assertArrayHasKey('rss_feed', $result->types);
        $this->assertArrayNotHasKey('newsletter', $result->types);
        $this->assertEquals(1, $result->types['website']); // 1 inactive website
        $this->assertEquals(1, $result->types['rss_feed']); // 1 auto-disabled rss_feed
    }

    public function testProvideThrowsExceptionWhenInvalidStatus(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440005';
        $this->setupWatchFile($watchFileId);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid status value. Must be "active" or "inactive".');

        $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'status' => 'invalid',
            ],
        ]);
    }

    public function testProvideWithNameFilter(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440007';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create sources with different names
        $source1 = new Source(
            'Acme Website',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://acme.com',
            'acme.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            'Beta RSS Feed',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::RSS_FEED,
            'https://beta.com/rss',
            'beta.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source2);

        $source3 = new Source(
            'Acme Newsletter',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::NEWSLETTER,
            'https://acme.com/newsletter',
            'acme.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source3);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'name' => 'acme',
            ],
        ]);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(2, $result->types);
        $this->assertArrayHasKey('website', $result->types);
        $this->assertArrayHasKey('newsletter', $result->types);
        $this->assertArrayNotHasKey('rss_feed', $result->types);
        $this->assertEquals(1, $result->types['website']);
        $this->assertEquals(1, $result->types['newsletter']);
    }

    public function testProvideWithNameFilterOnDomain(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440008';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create sources with different domains
        $source1 = new Source(
            'Source One',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            'Source Two',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::RSS_FEED,
            'https://other.org/rss',
            'other.org',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $this->sourceGateway->save($source2);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        // Filter by domain
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'name' => 'example',
            ],
        ]);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(1, $result->types);
        $this->assertArrayHasKey('website', $result->types);
        $this->assertArrayNotHasKey('rss_feed', $result->types);
        $this->assertEquals(1, $result->types['website']);
    }

    public function testProvideWithNameAndStatusFilter(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $watchFileId = '550e8400-e29b-41d4-a716-446655440009';
        $watchFile = $this->setupWatchFile($watchFileId);

        // Create sources with different names and statuses
        $source1 = new Source(
            'Acme Website Active',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://acme.com',
            'acme.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $source1->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source1);

        $source2 = new Source(
            'Acme Website Inactive',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::WEBSITE,
            'https://acme.com/old',
            'acme.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $source2->setStatus(SourceStatus::INACTIVE);
        $this->sourceGateway->save($source2);

        $source3 = new Source(
            'Beta RSS Active',
            TranslatedText::fromArray([
                'fr' => 'Description',
                'en' => 'Description',
            ]),
            SourceType::RSS_FEED,
            'https://beta.com/rss',
            'beta.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            null,
            $watchFile
        );
        $source3->setStatus(SourceStatus::ACTIVE);
        $this->sourceGateway->save($source3);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with('WATCH_FILE_VIEW', $this->anything())
            ->willReturn(true);

        // Filter by name AND active status
        $result = $this->provider->provide($this->operation, [
            'watchFileId' => $watchFileId,
        ], [
            'filters' => [
                'name' => 'acme',
                'status' => 'active',
            ],
        ]);

        $this->assertInstanceOf(SourceTypesDto::class, $result);
        $this->assertCount(1, $result->types);
        $this->assertArrayHasKey('website', $result->types);
        $this->assertEquals(1, $result->types['website']); // Only 1 active Acme website
    }
}
