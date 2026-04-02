<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Document;

use App\Domain\Document\Document;
use App\Domain\Document\DocumentStatus;
use App\Domain\Organisation\Organisation;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\Document\DocumentOpenSearchGateway;
use App\Tests\Utils\EntityUtilsTrait;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Verifies that DocumentOpenSearchGateway passes organisation_id as routing
 * on write operations, enabling tenant-based shard isolation in OpenSearch.
 */
#[CoversClass(DocumentOpenSearchGateway::class)]
class DocumentOpenSearchRoutingTest extends TestCase
{
    use EntityUtilsTrait;

    #[Test]
    public function savePassesRoutingFromOrganisationId(): void
    {
        $openSearch = $this->createMock(Client::class);
        $openSearch
            ->expects($this->once())
            ->method('index')
            ->with($this->callback(function (array $params) {
                return isset($params['routing'])
                    && 'org-a-id' === $params['routing'];
            }));

        $organisation = $this->makeOrganisation('org-a-id');
        $watchFile = new WatchFile('WF', 'objective', $organisation);
        $document = $this->makeDocument('doc-1', $watchFile);

        $this->makeGateway($openSearch)
->save($document);
    }

    #[Test]
    public function organisationIdIsDerivedFromWatchFile(): void
    {
        $organisation = $this->makeOrganisation('org-a-id');
        $watchFile = new WatchFile('WF', 'objective', $organisation);
        $document = $this->makeDocument('doc-1', $watchFile);

        $this->assertSame('org-a-id', $document->getOrganisationId());
    }

    #[Test]
    public function saveDoesNotPassRoutingWhenNoOrganisation(): void
    {
        $openSearch = $this->createMock(Client::class);
        $openSearch
            ->expects($this->once())
            ->method('index')
            ->with($this->callback(function (array $params) {
                return !isset($params['routing']);
            }));

        $document = $this->makeDocument('doc-2', null);

        $this->makeGateway($openSearch)
->save($document);
    }

    #[Test]
    public function saveBulkPassesRoutingPerDocument(): void
    {
        $openSearch = $this->createMock(Client::class);
        $openSearch
            ->expects($this->once())
            ->method('bulk')
            ->with($this->callback(function (array $params) {
                $body = $params['body'];

                // Each document contributes 2 entries: action + source
                $actionA = $body[0]['index'] ?? null;
                $actionB = $body[2]['index'] ?? null;

                return null !== $actionA
                    && 'org-a-id' === ($actionA['routing'] ?? null)
                    && null !== $actionB
                    && 'org-b-id' === ($actionB['routing'] ?? null);
            }));

        $docA = $this->makeDocument('doc-a', new WatchFile('WF A', 'objective', $this->makeOrganisation('org-a-id')));
        $docB = $this->makeDocument('doc-b', new WatchFile('WF B', 'objective', $this->makeOrganisation('org-b-id')));

        $this->makeGateway($openSearch)
->saveBulk([$docA, $docB]);
    }

    #[Test]
    public function saveBulkOmitsRoutingWhenNoOrganisation(): void
    {
        $openSearch = $this->createMock(Client::class);
        $openSearch
            ->expects($this->once())
            ->method('bulk')
            ->with($this->callback(function (array $params) {
                $action = $params['body'][0]['index'] ?? null;

                return null !== $action && !isset($action['routing']);
            }));

        $this->makeGateway($openSearch)
->saveBulk([$this->makeDocument('doc-no-org', null)]);
    }

    private function makeGateway(Client $client): DocumentOpenSearchGateway
    {
        $normalizer = $this->createStub(NormalizerInterface::class);
        $normalizer->method('normalize')
->willReturn([
    'id' => 'doc-id',
    'title' => 'Test',
]);

        return new DocumentOpenSearchGateway(
            $client,
            $this->createStub(DenormalizerInterface::class),
            $normalizer,
        );
    }

    private function makeOrganisation(string $id): Organisation
    {
        $organisation = new Organisation('Org', 'keycloak-id');
        $this->forcePropertyValue($organisation, $id);

        return $organisation;
    }

    private function makeDocument(string $id, ?WatchFile $watchFile): Document
    {
        $document = new Document(
            id: $id,
            title: 'Test Document',
            excerpt: 'Test excerpt for document',
            type: 'html',
            datePublish: new \DateTimeImmutable(),
            dateCollect: new \DateTimeImmutable(),
            content: '<p>Content</p>',
            status: DocumentStatus::PENDING,
        );

        if (null !== $watchFile) {
            $document->setWatchFile($watchFile);
        }

        return $document;
    }
}
