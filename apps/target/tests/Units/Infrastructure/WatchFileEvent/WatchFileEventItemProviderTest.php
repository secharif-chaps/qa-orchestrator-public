<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFileEvent;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\TranslatedText;
use App\Domain\WatchFile\Exception\WatchFileNotFoundException;
use App\Domain\WatchFile\WatchFile;
use App\Domain\WatchFile\WatchFileGatewayInterface;
use App\Domain\WatchFile\WatchFileSecurity;
use App\Domain\WatchFileEvent\EventType;
use App\Domain\WatchFileEvent\WatchFileEvent;
use App\Infrastructure\WatchFileEvent\WatchFileEventItemProvider;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class WatchFileEventItemProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private WatchFileEventItemProvider $provider;

    /**
     * @var ProviderInterface<WatchFileEvent>&MockObject
     */
    private ProviderInterface&MockObject $itemProvider;
    private WatchFileGatewayInterface&MockObject $watchFileGateway;
    private Security&MockObject $security;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->itemProvider = $this->createMock(ProviderInterface::class);
        $this->watchFileGateway = $this->createMock(WatchFileGatewayInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->operation = $this->createStub(Operation::class);

        $this->provider = new WatchFileEventItemProvider(
            $this->itemProvider,
            $this->watchFileGateway,
            $this->security,
        );
    }

    public function testProvideWithValidEventAndAuthorizedUser(): void
    {
        $esWatchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($esWatchFile, 'wf-id-123');

        $dbWatchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($dbWatchFile, 'wf-id-123');

        $event = new WatchFileEvent(
            id: 'event-id-123',
            startDate: new \DateTimeImmutable(),
            description: new TranslatedText('Description', 'Description'),
            eventType: EventType::COMMERCIAL_BUSINESS,
            watchFile: $esWatchFile,
            actors: [],
            documentLinks: [],
            title: new TranslatedText('Title', 'Titre'),
        );

        $uriVariables = [
            'id' => 'event-id-123',
        ];
        $context = [];

        $this->itemProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn($event);

        $this->watchFileGateway
            ->expects($this->once())
            ->method('get')
            ->with('wf-id-123')
            ->willReturn($dbWatchFile);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileSecurity::VIEW, $dbWatchFile)
            ->willReturn(true);

        $result = $this->provider->provide($this->operation, $uriVariables, $context);

        $this->assertSame($event, $result);
    }

    public function testProvideWithUnauthorizedUserDeniesAccess(): void
    {
        $esWatchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($esWatchFile, 'wf-id-123');

        $dbWatchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($dbWatchFile, 'wf-id-123');

        $event = new WatchFileEvent(
            id: 'event-id-123',
            startDate: new \DateTimeImmutable(),
            description: new TranslatedText('Description', 'Description'),
            eventType: EventType::COMMERCIAL_BUSINESS,
            watchFile: $esWatchFile,
            actors: [],
            documentLinks: [],
            title: new TranslatedText('Title', 'Titre'),
        );

        $this->itemProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn($event);

        $this->watchFileGateway
            ->expects($this->once())
            ->method('get')
            ->with('wf-id-123')
            ->willReturn($dbWatchFile);

        $this->security
            ->expects($this->once())
            ->method('isGranted')
            ->with(WatchFileSecurity::VIEW, $dbWatchFile)
            ->willReturn(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to view this event.');

        $this->provider->provide($this->operation, [
            'id' => 'event-id-123',
        ], []);
    }

    public function testProvideWithNullWatchFileDeniesAccess(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));

        $event = new WatchFileEvent(
            id: 'event-id-123',
            startDate: new \DateTimeImmutable(),
            description: new TranslatedText('Description', 'Description'),
            eventType: EventType::COMMERCIAL_BUSINESS,
            watchFile: $watchFile,
            actors: [],
            documentLinks: [],
            title: new TranslatedText('Title', 'Titre'),
        );
        $this->forcePropertyValue($event, null, 'watchFile');

        $this->itemProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn($event);

        $this->watchFileGateway
            ->expects($this->never())
            ->method('get');

        $this->security
            ->expects($this->never())
            ->method('isGranted');

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to view this event.');

        $this->provider->provide($this->operation, [
            'id' => 'event-id-123',
        ], []);
    }

    public function testProvideWithWatchFileNotFoundThrowsNotFound(): void
    {
        $esWatchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $this->forcePropertyValue($esWatchFile, 'wf-id-123');

        $event = new WatchFileEvent(
            id: 'event-id-123',
            startDate: new \DateTimeImmutable(),
            description: new TranslatedText('Description', 'Description'),
            eventType: EventType::COMMERCIAL_BUSINESS,
            watchFile: $esWatchFile,
            actors: [],
            documentLinks: [],
            title: new TranslatedText('Title', 'Titre'),
        );

        $this->itemProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn($event);

        $this->watchFileGateway
            ->expects($this->once())
            ->method('get')
            ->with('wf-id-123')
            ->willThrowException(new WatchFileNotFoundException('wf-id-123'));

        $this->security
            ->expects($this->never())
            ->method('isGranted');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The associated watchfile was not found.');

        $this->provider->provide($this->operation, [
            'id' => 'event-id-123',
        ], []);
    }

    public function testProvideWithNullResultReturnsNull(): void
    {
        $this->itemProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn(null);

        $this->watchFileGateway
            ->expects($this->never())
            ->method('get');

        $this->security
            ->expects($this->never())
            ->method('isGranted');

        $result = $this->provider->provide($this->operation, [
            'id' => 'non-existent',
        ], []);

        $this->assertNull($result);
    }

    public function testProvideWithNonWatchFileEventResultReturnsNull(): void
    {
        $this->itemProvider
            ->expects($this->once())
            ->method('provide')
            ->willReturn(new \stdClass());

        $this->watchFileGateway
            ->expects($this->never())
            ->method('get');

        $this->security
            ->expects($this->never())
            ->method('isGranted');

        $result = $this->provider->provide($this->operation, [
            'id' => 'some-id',
        ], []);

        $this->assertNull($result);
    }
}
