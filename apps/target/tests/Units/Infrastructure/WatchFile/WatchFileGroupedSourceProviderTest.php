<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile;

use ApiPlatform\Metadata\Get;
use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\CollectStatus;
use App\Domain\Source\Source;
use App\Domain\Source\SourceStatus;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Infrastructure\WatchFile\WatchFileGroupedSourceProvider;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\UserInterface\Dto\Source\SourceGroupOutput;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class WatchFileGroupedSourceProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private Security&Stub $security;
    private WatchFileGatewayInterface $watchFileGateway;
    private NullSourceGateway $sourceGateway;
    private WatchFileGroupedSourceProvider $provider;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $this->provider = new WatchFileGroupedSourceProvider(
            $this->watchFileGateway,
            $this->sourceGateway,
            $this->security
        );
    }

    public function testProvideWithValidWatchFileAndAccessGranted(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        // Create test sources with different types and statuses
        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        $rssSource = new Source(
            'RSS Source',
            TranslatedText::fromArray([
                'fr' => 'RSS feed source',
                'en' => 'RSS feed source',
            ]),
            SourceType::RSS_FEED,
            'https://example.com/rss',
            'example.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($rssSource, 'source1');
        $rssSource->setStatus(SourceStatus::ACTIVE);
        $rssSource->setCollectStatus(CollectStatus::RUNNING);

        $youtubeSource = new Source(
            'YouTube Source',
            TranslatedText::fromArray([
                'fr' => 'YouTube channel source',
                'en' => 'YouTube channel source',
            ]),
            SourceType::VIDEO_YOUTUBE_CHANNEL,
            'https://youtube.com/channel',
            'youtube.com',
            TranslatedText::fromArray([
                'fr' => '0.9',
                'en' => '0.9',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($youtubeSource, 'source2');
        $youtubeSource->setStatus(SourceStatus::ACTIVE);
        $youtubeSource->setCollectStatus(CollectStatus::RUNNING);

        $errorSource = new Source(
            'Error Source',
            TranslatedText::fromArray([
                'fr' => 'Error source',
                'en' => 'Error source',
            ]),
            SourceType::WEBSITE,
            'https://error.com',
            'error.com',
            TranslatedText::fromArray([
                'fr' => '0.5',
                'en' => '0.5',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($errorSource, 'source3');
        $errorSource->setCollectStatus(CollectStatus::ERROR);

        $inactiveSource = new Source(
            'Inactive Source',
            TranslatedText::fromArray([
                'fr' => 'Inactive source',
                'en' => 'Inactive source',
            ]),
            SourceType::BLOG,
            'https://inactive.com',
            'inactive.com',
            TranslatedText::fromArray([
                'fr' => '0.6',
                'en' => '0.6',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($inactiveSource, 'source4');
        $inactiveSource->setStatus(SourceStatus::INACTIVE);
        $inactiveSource->setCollectStatus(CollectStatus::STOPPED);

        // Add sources to gateways
        $this->watchFileGateway->save($watchFile);
        $this->sourceGateway->save($rssSource);
        $this->sourceGateway->save($youtubeSource);
        $this->sourceGateway->save($errorSource);
        $this->sourceGateway->save($inactiveSource);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Act
        $result = $this->provider->provide($operation, $uriVariables, $context);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $this->assertCount(4, $groups); // Error group + 3 type groups (rss_feed, video_youtube_channel, website, blog)

        // Check error group
        $errorGroup = $groups[0];
        $this->assertEquals('error', $errorGroup->getType());
        $this->assertEquals('testerror', $errorGroup->getTypeLabel());
        $this->assertEquals(1, $errorGroup->getCount());

        // Check summary
        $summary = $result->summary;
        $this->assertEquals(4, $summary['total']);
        $this->assertEquals(1, $summary['error']);
        $this->assertEquals(2, $summary['running']); // 2 ACTIVE sources
        $this->assertEquals(1, $summary['stopped']); // 1 INACTIVE source
    }

    public function testProvideWithAccessDenied(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $this->watchFileGateway->save($watchFile);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have access to this watchfile.');

        $this->provider->provide($operation, $uriVariables, $context);
    }

    public function testProvideWithWatchFileNotFound(): void
    {
        // Arrange
        $operation = new Get();
        $uriVariables = [
            'id' => 'nonexistent',
        ];
        $context = [];

        // Act & Assert
        $this->expectException(WatchFileNotFoundException::class);

        $this->provider->provide($operation, $uriVariables, $context);
    }

    public function testProvideWithEmptySources(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $this->watchFileGateway->save($watchFile);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Act
        $result = $this->provider->provide($operation, $uriVariables, $context);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $this->assertEmpty($groups);

        $summary = $result->summary;
        $this->assertEquals(0, $summary['total']);
        $this->assertEquals(0, $summary['error']);
        $this->assertEquals(0, $summary['running']);
        $this->assertEquals(0, $summary['stopped']);
    }

    public function testProvideWithMixedSourceStatuses(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        // Arrange
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        // Create sources with different statuses
        $activeSource = new Source(
            'Active Source',
            TranslatedText::fromArray([
                'fr' => 'Active source',
                'en' => 'Active source',
            ]),
            SourceType::RSS_FEED,
            'https://active.com',
            'active.com',
            TranslatedText::fromArray([
                'fr' => '0.8',
                'en' => '0.8',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($activeSource, 'source1');
        $activeSource->setStatus(SourceStatus::ACTIVE);
        $activeSource->setCollectStatus(CollectStatus::RUNNING);

        $autoDisabledSource = new Source(
            'Auto Disabled Source',
            TranslatedText::fromArray([
                'fr' => 'Auto disabled source',
                'en' => 'Auto disabled source',
            ]),
            SourceType::RSS_FEED,
            'https://autodisabled.com',
            'autodisabled.com',
            TranslatedText::fromArray([
                'fr' => '0.7',
                'en' => '0.7',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($autoDisabledSource, 'source2');
        $autoDisabledSource->setStatus(SourceStatus::AUTO_DISABLED);
        $autoDisabledSource->setCollectStatus(CollectStatus::STOPPED);

        $errorSource = new Source(
            'Error Source',
            TranslatedText::fromArray([
                'fr' => 'Error source',
                'en' => 'Error source',
            ]),
            SourceType::RSS_FEED,
            'https://error.com',
            'error.com',
            TranslatedText::fromArray([
                'fr' => '0.5',
                'en' => '0.5',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($errorSource, 'source3');
        $errorSource->setCollectStatus(CollectStatus::ERROR);

        $this->watchFileGateway->save($watchFile);
        $this->sourceGateway->save($activeSource);
        $this->sourceGateway->save($autoDisabledSource);
        $this->sourceGateway->save($errorSource);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => 'watchfile1',
        ];
        $context = [];

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        // Act
        $result = $this->provider->provide($operation, $uriVariables, $context);

        // Assert
        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $summary = $result->summary;
        $this->assertEquals(3, $summary['total']);
        $this->assertEquals(1, $summary['error']);
        $this->assertEquals(1, $summary['running']); // 1 ACTIVE
        $this->assertEquals(1, $summary['stopped']); // 1 AUTO_DISABLED
    }

    public function testProvideWithSearchQueryOnName(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $watchFileId = $watchFile->getId();

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        $techcrunchSource = new Source(
            'TechCrunch - Health Tech',
            TranslatedText::fromArray([
                'fr' => 'TechCrunch Health Tech',
                'en' => 'TechCrunch Health Tech',
            ]),
            SourceType::BLOG,
            'https://techcrunch.com/category/health/',
            'techcrunch.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($techcrunchSource, 'source1');
        $techcrunchSource->setStatus(SourceStatus::ACTIVE);
        $techcrunchSource->setCollectStatus(CollectStatus::STOPPED);

        $natureSource = new Source(
            'Nature - Energy Research',
            TranslatedText::fromArray([
                'fr' => 'Nature Energy Research',
                'en' => 'Nature Energy Research',
            ]),
            SourceType::WEBSITE,
            'https://www.nature.com/subjects/energy',
            'nature.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($natureSource, 'source2');
        $natureSource->setStatus(SourceStatus::ACTIVE);
        $natureSource->setCollectStatus(CollectStatus::STOPPED);

        $forresterSource = new Source(
            'Forrester - Competitive Intelligence',
            TranslatedText::fromArray([
                'fr' => 'Forrester Competitive Intelligence',
                'en' => 'Forrester Competitive Intelligence',
            ]),
            SourceType::WEBSITE,
            'https://www.forrester.com/topic/competitive-intelligence',
            'forrester.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($forresterSource, 'source3');
        $forresterSource->setStatus(SourceStatus::ACTIVE);
        $forresterSource->setCollectStatus(CollectStatus::STOPPED);

        $this->sourceGateway->save($techcrunchSource);
        $this->sourceGateway->save($natureSource);
        $this->sourceGateway->save($forresterSource);

        $this->watchFileGateway->save($watchFile);
        $this->security->method('isGranted')
->willReturn(true);

        $request = new Request([
            'search' => 'techcrunch',
        ]);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'request' => $request,
        ];

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $totalSources = array_sum(array_map(fn ($group) => $group->getTotal(), $groups));
        $this->assertEquals(1, $totalSources);

        $allSources = [];
        foreach ($groups as $group) {
            foreach ($group->sources as $source) {
                $allSources[] = $source->getName();
            }
        }
        $this->assertContains('TechCrunch - Health Tech', $allSources);
        $this->assertNotContains('Nature - Energy Research', $allSources);
        $this->assertNotContains('Forrester - Competitive Intelligence', $allSources);
    }

    public function testProvideWithSearchQueryOnDomain(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $watchFileId = $watchFile->getId();

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        $techcrunchSource = new Source(
            'TechCrunch - Health Tech',
            TranslatedText::fromArray([
                'fr' => 'TechCrunch Health Tech',
                'en' => 'TechCrunch Health Tech',
            ]),
            SourceType::BLOG,
            'https://techcrunch.com/category/health/',
            'techcrunch.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($techcrunchSource, 'source1');
        $techcrunchSource->setStatus(SourceStatus::ACTIVE);
        $techcrunchSource->setCollectStatus(CollectStatus::STOPPED);

        $natureSource = new Source(
            'Nature - Energy Research',
            TranslatedText::fromArray([
                'fr' => 'Nature Energy Research',
                'en' => 'Nature Energy Research',
            ]),
            SourceType::WEBSITE,
            'https://www.nature.com/subjects/energy',
            'nature.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($natureSource, 'source2');
        $natureSource->setStatus(SourceStatus::ACTIVE);
        $natureSource->setCollectStatus(CollectStatus::STOPPED);

        $this->sourceGateway->save($techcrunchSource);
        $this->sourceGateway->save($natureSource);
        $this->watchFileGateway->save($watchFile);
        $this->security->method('isGranted')
->willReturn(true);

        $request = new Request([
            'search' => 'nature.com',
        ]);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'request' => $request,
        ];

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $totalSources = array_sum(array_map(fn ($group) => $group->getTotal(), $groups));
        $this->assertEquals(1, $totalSources);

        $allSources = [];
        foreach ($groups as $group) {
            foreach ($group->sources as $source) {
                $allSources[] = $source->getName();
            }
        }
        $this->assertContains('Nature - Energy Research', $allSources);
        $this->assertNotContains('TechCrunch - Health Tech', $allSources);
    }

    public function testProvideWithSearchQueryTooShort(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $watchFileId = $watchFile->getId();

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        $techcrunchSource = new Source(
            'TechCrunch - Health Tech',
            TranslatedText::fromArray([
                'fr' => 'TechCrunch Health Tech',
                'en' => 'TechCrunch Health Tech',
            ]),
            SourceType::BLOG,
            'https://techcrunch.com/category/health/',
            'techcrunch.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($techcrunchSource, 'source1');
        $techcrunchSource->setStatus(SourceStatus::ACTIVE);
        $techcrunchSource->setCollectStatus(CollectStatus::STOPPED);

        $this->sourceGateway->save($techcrunchSource);
        $this->watchFileGateway->save($watchFile);
        $this->security->method('isGranted')
->willReturn(true);

        $request = new Request([
            'search' => 't',
        ]);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'request' => $request,
        ];

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $totalSources = array_sum(array_map(fn ($group) => $group->getTotal(), $groups));
        $this->assertEquals(1, $totalSources);
    }

    public function testProvideWithSearchQueryNoResults(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $watchFileId = $watchFile->getId();

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        $techcrunchSource = new Source(
            'TechCrunch - Health Tech',
            TranslatedText::fromArray([
                'fr' => 'TechCrunch Health Tech',
                'en' => 'TechCrunch Health Tech',
            ]),
            SourceType::BLOG,
            'https://techcrunch.com/category/health/',
            'techcrunch.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($techcrunchSource, 'source1');
        $techcrunchSource->setStatus(SourceStatus::ACTIVE);
        $techcrunchSource->setCollectStatus(CollectStatus::STOPPED);

        $this->sourceGateway->save($techcrunchSource);
        $this->watchFileGateway->save($watchFile);
        $this->security->method('isGranted')
->willReturn(true);

        $request = new Request([
            'search' => 'nonexistent',
        ]);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'request' => $request,
        ];

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $totalSources = array_sum(array_map(fn ($group) => $group->getTotal(), $groups));
        $this->assertEquals(0, $totalSources);

        $summary = $result->summary;
        $this->assertEquals(0, $summary['total']);
    }

    public function testProvideWithSearchQueryCaseInsensitive(): void
    {
        $user = new User('user1', 'user1@example.com', [], 'User 1');
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation(
            'Test Org',
            'test-org-id'
        ), $user);
        $this->forcePropertyValue($watchFile, 'watchfile1');
        $watchFileId = $watchFile->getId();

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($actor, 'actor1');

        $techcrunchSource = new Source(
            'TechCrunch - Health Tech',
            TranslatedText::fromArray([
                'fr' => 'TechCrunch Health Tech',
                'en' => 'TechCrunch Health Tech',
            ]),
            SourceType::BLOG,
            'https://techcrunch.com/category/health/',
            'techcrunch.com',
            TranslatedText::fromArray([
                'fr' => 'N/A',
                'en' => 'N/A',
            ]),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($techcrunchSource, 'source1');
        $techcrunchSource->setStatus(SourceStatus::ACTIVE);
        $techcrunchSource->setCollectStatus(CollectStatus::STOPPED);

        $this->sourceGateway->save($techcrunchSource);
        $this->watchFileGateway->save($watchFile);
        $this->security->method('isGranted')
->willReturn(true);

        $request = new Request([
            'search' => 'TECHCRUNCH',
        ]);

        $operation = new Get();
        $uriVariables = [
            'watchFileId' => $watchFileId,
        ];
        $context = [
            'request' => $request,
        ];

        $result = $this->provider->provide($operation, $uriVariables, $context);

        $this->assertNotNull($result);
        $this->assertInstanceOf(SourceGroupOutput::class, $result);

        $groups = $result->groups;
        $totalSources = array_sum(array_map(fn ($group) => $group->getTotal(), $groups));
        $this->assertEquals(1, $totalSources);
    }
}
