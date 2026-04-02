<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\Factory\Actor\ActorFactory;
use App\DataFixtures\Factory\Document\DocumentFactory;
use App\DataFixtures\Factory\Source\SourceFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\DataFixtures\Factory\WatchFile\WatchFileFactory;
use App\Domain\Document\DocumentGatewayInterface;
use App\Domain\Document\DocumentStatus;
use App\Domain\Document\ManualValidationStatus;
use App\Tests\Utils\OpenSearchUtilsTrait;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class ManualValidateDocumentIntegrationApiTest extends AbstractApiTestCase
{
    use Factories;
    use OpenSearchUtilsTrait;
    use ResetDatabase;

    protected function tearDown(): void
    {
        $this->cleanupOpenSearch();
        parent::tearDown();
    }

    public function testManualValidationTransitionFromAcceptedToUncertain(): void
    {
        /** @var DocumentGatewayInterface $documentGateway */
        $documentGateway = self::getContainer()->get(DocumentGatewayInterface::class);

        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
            'primaryDomain' => 'test.com',
        ]);

        $source = SourceFactory::new()->create([
            'name' => 'Test Source',
            'primaryDomain' => 'test.com',
        ]);

        $document = DocumentFactory::new()
            ->withWatchFile($watchFile)
            ->withActor($actor)
            ->withSource($source)
            ->withStatus(DocumentStatus::PENDING)
            ->create();

        $document->manuallyValidate(ManualValidationStatus::ACCEPTED, $user);
        $documentGateway->save($document);
        $this->refreshOpenSearchIndex();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request(
            'POST',
            '/api/documents/' . $document->getId() . '/manual-validate',
            [
                'json' => [
                    'action' => ManualValidationStatus::UNCERTAIN->value,
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals($document->getId(), $data['document_id']);
        $this->assertNull($data['manual_status']);
        $this->assertNull($data['validated_by']);
        $this->assertNull($data['validated_at']);
    }

    public function testManualValidationTransitionFromRefusedToUncertain(): void
    {
        /** @var DocumentGatewayInterface $documentGateway */
        $documentGateway = self::getContainer()->get(DocumentGatewayInterface::class);

        $user = UserFactory::createOne();
        $watchFile = WatchFileFactory::new()
            ->withCreatedBy($user)
            ->withOwnedBy($user)
            ->create();

        $actor = ActorFactory::createOne([
            'label' => 'Test Actor',
            'primaryDomain' => 'test.com',
        ]);

        $source = SourceFactory::new()->create([
            'name' => 'Test Source',
            'primaryDomain' => 'test.com',
        ]);

        $document = DocumentFactory::new()
            ->withWatchFile($watchFile)
            ->withActor($actor)
            ->withSource($source)
            ->withStatus(DocumentStatus::PENDING)
            ->create();

        $document->manuallyValidate(ManualValidationStatus::REFUSED, $user);
        $documentGateway->save($document);
        $this->refreshOpenSearchIndex();

        $client = $this->createAuthenticatedClient($user);
        $response = $client->request(
            'POST',
            '/api/documents/' . $document->getId() . '/manual-validate',
            [
                'json' => [
                    'action' => ManualValidationStatus::UNCERTAIN->value,
                ],
            ]
        );

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertTrue($data['success']);
        $this->assertEquals($document->getId(), $data['document_id']);
        $this->assertNull($data['manual_status']);
        $this->assertNull($data['validated_by']);
        $this->assertNull($data['validated_at']);
    }
}
