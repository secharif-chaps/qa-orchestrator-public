<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\SourceActivity;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceGatewayInterface;
use App\Domain\Source\SourceType;
use App\Domain\SourceActivity\SourceActivity;
use App\Domain\SourceActivity\SourceActivityActionType;
use App\Domain\SourceActivity\SourceActivityGatewayInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\SourceActivity\SourceActivityProvider;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Webmozart\Assert\InvalidArgumentException;

class SourceActivityProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private SourceActivityGatewayInterface $sourceActivityGateway;
    private SourceGatewayInterface $sourceGateway;
    private Security $security;
    private SourceActivityProvider $provider;

    protected function setUp(): void
    {
        $this->sourceActivityGateway = new NullSourceActivityGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->security = $this->createStub(Security::class);
        $this->buildProvider();
    }

    private function buildProvider(): void
    {
        $pagination = new Pagination([
            'items_per_page' => 20,
        ]);
        $this->provider = new SourceActivityProvider(
            $this->sourceActivityGateway,
            $this->sourceGateway,
            $this->security,
            $pagination
        );
    }

    public function testProvideWithNullSourceId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source ID must be provided');

        $this->provider->provide($this->createStub(Operation::class), []);
    }

    public function testProvideWithEmptySourceId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Source ID must be a non-empty string');

        $this->provider->provide($this->createStub(Operation::class), [
            'sourceId' => '',
        ]);
    }

    public function testProvideWithNonExistentSource(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Source not found');

        $this->provider->provide($this->createStub(Operation::class), [
            'sourceId' => 'non-existent-id',
        ]);
    }

    public function testProvideWithAccessDenied(): void
    {
        // Create a user and watchfile
        $user = new User(id: 'test-user-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'testuser');

        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Test objective',
            organisation: new Organisation('Test Org', 'test-org-id'),
            createdBy: $user
        );

        $this->forcePropertyValue($watchFile, 'watch_file_id');

        // Create a source with the watchfile
        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinent FR', 'Relevant EN'),
            actor: null,
            watchFile: $watchFile
        );

        $this->forcePropertyValue($source, 'source_id');
        $this->sourceGateway->save($source);

        $securityMock = $this->createMock(Security::class);
        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $pagination = new Pagination([
            'items_per_page' => 20,
        ]);
        $provider = new SourceActivityProvider(
            $this->sourceActivityGateway,
            $this->sourceGateway,
            $securityMock,
            $pagination
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You do not have access to this watch file.');

        $provider->provide($this->createStub(Operation::class), [
            'sourceId' => $source->getId(),
        ]);
    }

    public function testProvideSuccessfully(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User(id: 'test-user-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'testuser');
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Test objective',
            organisation: new Organisation('Test Org', 'test-org-id'),
            createdBy: $user
        );
        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinent FR', 'Relevant EN'),
            actor: null,
            watchFile: $watchFile
        );

        $this->forcePropertyValue($user, 'id');
        $this->forcePropertyValue($watchFile, 'id');
        $this->forcePropertyValue($source, 'id');
        $this->sourceGateway->save($source);

        $activity1 = new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_CONNECTED,
            [
                'test_data' => 'value1',
            ]
        );
        $activity2 = new SourceActivity(
            $source,
            $user,
            SourceActivityActionType::SOURCE_DATA_RETRIEVED,
            [
                'test_data' => 'value2',
            ]
        );

        $this->forcePropertyValue($activity1, 'id');
        $this->forcePropertyValue($activity2, 'id');

        $this->sourceActivityGateway->save($activity1);
        $this->sourceActivityGateway->save($activity2);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $result = $this->provider->provide(
            $this->createStub(Operation::class),
            [
                'sourceId' => $source->getId(),
            ]
        );

        $this->assertEquals(2, $result->count());
    }

    public function testProvideWithActivitiesAcrossMultipleDays(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProvider();
        $user = new User(id: 'test-user-id', email: 'test@example.com', roles: ['ROLE_USER'], userName: 'testuser');
        $watchFile = new WatchFile(
            name: 'Test WatchFile',
            userObjective: 'Test objective',
            organisation: new Organisation('Test Org', 'test-org-id'),
            createdBy: $user
        );
        $source = new Source(
            name: 'Test Source',
            description: new TranslatedText('Description FR', 'Description EN'),
            type: SourceType::WEBSITE,
            url: 'https://example.com',
            primaryDomain: 'example.com',
            relevance: new TranslatedText('Pertinent FR', 'Relevant EN'),
            actor: null,
            watchFile: $watchFile
        );

        $this->forcePropertyValue($user, 'id');
        $this->forcePropertyValue($watchFile, 'id');
        $this->forcePropertyValue($source, 'id');

        $this->sourceGateway->save($source);

        $today = new \DateTime();
        $allActivities = [];
        for ($day = 0; $day < 3; ++$day) {
            $date = (clone $today)->modify("-{$day} days");

            $activity1 = new SourceActivity(
                $source,
                $user,
                SourceActivityActionType::SOURCE_CONNECTED,
                [
                    'test_data' => "value1_day_$day",
                ]
            );
            $activity2 = new SourceActivity(
                $source,
                $user,
                SourceActivityActionType::SOURCE_DATA_RETRIEVED,
                [
                    'test_data' => "value2_day_$day",
                ]
            );

            $this->forcePropertyValue($activity1, $date, 'createdAt');
            $this->forcePropertyValue($activity2, $date, 'createdAt');
            $this->forcePropertyValue($activity1, 'id');
            $this->forcePropertyValue($activity2, 'id');

            $allActivities[] = $activity1;
            $allActivities[] = $activity2;
        }

        foreach ($allActivities as $activity) {
            $this->sourceActivityGateway->save($activity);
        }

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $result = $this->provider->provide(
            $this->createStub(Operation::class),
            [
                'sourceId' => $source->getId(),
            ]
        );

        $this->assertEquals(6, $result->count());

        $activitiesByDay = $result->getActivitiesByDay();
        $this->assertCount(3, $activitiesByDay);

        foreach ($activitiesByDay as $dayActivities) {
            $this->assertCount(2, $dayActivities);
        }
    }
}
