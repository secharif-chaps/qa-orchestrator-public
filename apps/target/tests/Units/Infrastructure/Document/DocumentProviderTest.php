<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\Shared\EntityEnrichmentOrchestratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\DocumentProvider;
use App\Infrastructure\WatchFile\Voter\WatchFileVoter;
use App\Tests\Utils\EntityUtilsTrait;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[AllowMockObjectsWithoutExpectations]
class DocumentProviderTest extends TestCase
{
    use EntityUtilsTrait;
    private DocumentProvider $provider;

    /**
     * @var ProviderInterface<Document>&MockObject
     */
    private ProviderInterface&MockObject $apiPlatformProvider;
    private EntityEnrichmentOrchestratorInterface&MockObject $entityEnrichmentOrchestrator;
    private Security&MockObject $security;
    private Operation&Stub $operation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiPlatformProvider = $this->createMock(ProviderInterface::class);
        $this->entityEnrichmentOrchestrator = $this->createMock(EntityEnrichmentOrchestratorInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->operation = $this->createStub(Operation::class);

        $this->provider = new DocumentProvider(
            $this->apiPlatformProvider,
            $this->entityEnrichmentOrchestrator,
            $this->security,
        );
    }

    public function testProvideWithValidDocument(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $document = new Document(
            id: '550e8400-e29b-41d4-a716-446655440001',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: '<p>Test content</p>',
            status: DocumentStatus::VALIDATED,
        );
        $this->forcePropertyValue($document, $watchFile, 'watchFile');

        $user = new User('user1', 'user1@example.com', [], 'user1');
        $enrichedDocument = clone $document;

        $uriVariables = [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
        ];
        $context = [
            'test' => 'context',
        ];

        $this->apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn($document);

        $this->security
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(true);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $this->entityEnrichmentOrchestrator
            ->expects($this->once())
            ->method('enrich')
            ->with($document, [
                'user' => $user,
            ])
            ->willReturn($enrichedDocument);

        $result = $this->provider->provide($this->operation, $uriVariables, $context);

        $this->assertSame($enrichedDocument, $result);
    }

    public function testProvideWithDocumentWithoutWatchFileDeniesAccess(): void
    {
        $document = new Document(
            id: '550e8400-e29b-41d4-a716-446655440001',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: '<p>Test content</p>',
            status: DocumentStatus::VALIDATED,
        );

        $uriVariables = [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
        ];
        $context = [
            'test' => 'context',
        ];

        $this->apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn($document);

        $this->entityEnrichmentOrchestrator
            ->expects($this->never())
            ->method('enrich');

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to view this document.');

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithUnauthorizedUserDeniesAccess(): void
    {
        $watchFile = new WatchFile('Test WatchFile', 'Test objective', new Organisation('Test Org', 'test-org-id'));
        $document = new Document(
            id: '550e8400-e29b-41d4-a716-446655440001',
            title: 'Test Document',
            excerpt: 'Test excerpt',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: '<p>Test content</p>',
            status: DocumentStatus::VALIDATED,
        );
        $this->forcePropertyValue($document, $watchFile, 'watchFile');

        $uriVariables = [
            'id' => '550e8400-e29b-41d4-a716-446655440001',
        ];
        $context = [
            'test' => 'context',
        ];

        $this->apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn($document);

        $this->security
            ->method('isGranted')
            ->with(WatchFileVoter::VIEW, $watchFile)
            ->willReturn(false);

        $this->entityEnrichmentOrchestrator
            ->expects($this->never())
            ->method('enrich');

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('You do not have permission to view this document.');

        $this->provider->provide($this->operation, $uriVariables, $context);
    }

    public function testProvideWithNullResult(): void
    {
        $uriVariables = [
            'id' => 'non-existent-id',
        ];
        $context = [
            'test' => 'context',
        ];

        $this->apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn(null);

        $this->security
            ->expects($this->never())
            ->method('getUser');

        $this->entityEnrichmentOrchestrator
            ->expects($this->never())
            ->method('enrich');

        $result = $this->provider->provide($this->operation, $uriVariables, $context);

        $this->assertNull($result);
    }

    public function testProvideWithNonDocumentResult(): void
    {
        $nonDocument = new \stdClass();
        $uriVariables = [
            'id' => 'some-id',
        ];
        $context = [
            'test' => 'context',
        ];

        $this->apiPlatformProvider
            ->expects($this->once())
            ->method('provide')
            ->with($this->operation, $uriVariables, $context)
            ->willReturn($nonDocument);

        $this->security
            ->expects($this->never())
            ->method('getUser');

        $this->entityEnrichmentOrchestrator
            ->expects($this->never())
            ->method('enrich');

        $result = $this->provider->provide($this->operation, $uriVariables, $context);

        $this->assertNull($result);
    }
}
