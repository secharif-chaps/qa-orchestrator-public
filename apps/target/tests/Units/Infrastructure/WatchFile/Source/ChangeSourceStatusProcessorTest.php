<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile\Source;

use App\Application\WatchFile\Source\ChangeSourceStatusAction;
use App\Application\WatchFile\Source\ChangeSourceStatusHandler;
use App\Domain\Actor\Actor;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\Source\Source;
use App\Domain\Source\SourceType;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\Source\ChangeSourceStatusProcessor;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Units\Infrastructure\Source\NullSourceGateway;
use App\Tests\Units\Infrastructure\WatchFile\NullWatchFileGateway;
use App\Tests\Utils\EntityUtilsTrait;
use App\Tests\Utils\Symfony\NullMessageBus;
use App\UserInterface\Dto\Source\ChangeSourceStatusDto;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Webmozart\Assert\Assert;

class ChangeSourceStatusProcessorTest extends TestCase
{
    use EntityUtilsTrait;
    private Security $security;
    private ChangeSourceStatusProcessor $processor;
    private NullWatchFileGateway $watchFileGateway;
    private NullSourceGateway $sourceGateway;

    protected function setUp(): void
    {
        $this->watchFileGateway = new NullWatchFileGateway();
        $this->sourceGateway = new NullSourceGateway();
        $this->security = $this->createStub(Security::class);
        $this->buildProcessor();
    }

    private function buildProcessor(): void
    {
        $handler = new ChangeSourceStatusHandler(new NullMessageBus(), $this->createStub(
            EventDispatcherInterface::class
        ));
        $handler->setWatchFileGateway($this->watchFileGateway);
        $handler->setSourceGateway($this->sourceGateway);
        $this->processor = new ChangeSourceStatusProcessor(
            new NullMessageBus(function (object $action) use ($handler): mixed {
                Assert::isInstanceOf($action, ChangeSourceStatusAction::class);

                return $handler($action);
            }),
            $this->security,
            $this->watchFileGateway,
        );
    }

    public function testChangeSourceStatusToEnabled(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Description FR', en: 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'Relevance FR', en: 'Relevance EN'),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->deactivate(); // Start with inactive status
        $this->sourceGateway->save($source);

        $dto = new ChangeSourceStatusDto('active');

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);

        $result = $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch_file_id',
                'sourceId' => 'source_id',
            ]
        );

        $this->assertTrue($result->isActive());
        $this->assertSame($source, $result);
    }

    public function testChangeSourceStatusToDisabled(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $actor = new Actor('Test Actor', new Organisation('Test Org', 'test-org-id'));
        $source = new Source(
            'Test Source',
            new TranslatedText(fr: 'Description FR', en: 'Description EN'),
            SourceType::WEBSITE,
            'https://example.com',
            'example.com',
            new TranslatedText(fr: 'Relevance FR', en: 'Relevance EN'),
            $actor,
            $watchFile
        );
        $this->forcePropertyValue($source, 'source_id');
        $source->activate(); // Start with active status
        $this->sourceGateway->save($source);

        $dto = new ChangeSourceStatusDto('inactive');

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(true);

        $result = $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch_file_id',
                'sourceId' => 'source_id',
            ]
        );

        $this->assertFalse($result->isActive());
        $this->assertSame($source, $result);
    }

    public function testMissingWatchFileIdThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be provided in the URL');

        $dto = new ChangeSourceStatusDto('active');

        $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'sourceId' => 'source_id',
            ]
        );
    }

    public function testMissingSourceIdThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Source ID must be provided in the URL');

        $dto = new ChangeSourceStatusDto('active');

        $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch_file_id',
            ]
        );
    }

    public function testNonStringWatchFileIdThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('WatchFile ID must be a string');

        $dto = new ChangeSourceStatusDto('active');

        $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 123,
                'sourceId' => 'source_id',
            ]
        );
    }

    public function testNonStringSourceIdThrowsException(): void
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Source ID must be a string');

        $dto = new ChangeSourceStatusDto('active');

        $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch_file_id',
                'sourceId' => 456,
            ]
        );
    }

    public function testUnauthenticatedUserThrowsException(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $dto = new ChangeSourceStatusDto('active');

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch_file_id',
                'sourceId' => 'source_id',
            ]
        );
    }

    public function testAccessDeniedThrowsException(): void
    {
        $securityMock = $this->createMock(Security::class);
        $this->security = $securityMock;
        $this->buildProcessor();
        $user = new User('user-id', 'user@example.com');
        $watchFile = new WatchFile('Test WatchFile', 'Objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($watchFile, 'watch_file_id');
        $this->watchFileGateway->save($watchFile);

        $dto = new ChangeSourceStatusDto('active');

        $securityMock->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $securityMock->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileVoter::EDIT, $watchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('You are not allowed to change the status of this source');

        $this->processor->process(
            $dto,
            $this->createStub(\ApiPlatform\Metadata\Operation::class),
            [
                'watchFileId' => 'watch_file_id',
                'sourceId' => 'source_id',
            ]
        );
    }
}
