<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\DataFixtures\Factory\Organisation\OrganisationFactory;
use App\DataFixtures\Factory\User\UserFactory;
use App\Domain\Organisation\Organisation;
use App\Domain\User\User;
use App\Infrastructure\Organisation\EventSubscriber\TestTenantContextListener;
use App\Infrastructure\User\Security\TestAuthenticator;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class AbstractApiTestCase extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureUnaccentExtension();
    }

    protected function createAuthenticatedClient(?User $currentUser = null): Client
    {
        if (null === $currentUser) {
            $currentUser = UserFactory::new()
                ->defaultBasilUser()
                ->create()
            ;
        }

        $currentUserId = $currentUser->getId();
        if (null === $currentUserId) {
            throw new \LogicException('The user must have an ID to be used in tests.');
        }

        return self::createClient([], [
            'headers' => [
                TestAuthenticator::HEADER_TEST_AUTH_USER_ID => $currentUserId,
            ],
        ]);
    }

    protected function createAuthenticatedClientInOrganisation(
        ?User $currentUser = null,
        ?Organisation $organisation = null,
    ): Client {
        if (null === $currentUser) {
            $currentUser = UserFactory::new()
                ->defaultBasilUser()
                ->create()
            ;
        }

        if (null === $organisation) {
            $organisation = OrganisationFactory::createOne();
        }

        $currentUserId = $currentUser->getId();
        if (null === $currentUserId) {
            throw new \LogicException('The user must have an ID to be used in tests.');
        }

        return self::createClient([], [
            'headers' => [
                TestAuthenticator::HEADER_TEST_AUTH_USER_ID => $currentUserId,
                TestTenantContextListener::HEADER_TEST_AUTH_ORG_ID => $organisation->getKeycloakId(),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $kernelOptions Options to pass to the createKernel method
     * @param array{
     *     headers?: array<string, string>,
     * } $defaultOptions Default options for the requests
     */
    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        if (empty($defaultOptions['headers']['Content-Type'])) {
            $defaultOptions['headers']['Content-Type'] = 'application/ld+json';
        }

        if (empty($defaultOptions['headers']['Accept'])) {
            $defaultOptions['headers']['Accept'] = 'application/ld+json';
        }

        if (null === parent::$alwaysBootKernel) {
            parent::$alwaysBootKernel = false;
        }

        return parent::createClient($kernelOptions, $defaultOptions);
    }

    protected function ensureUnaccentExtension(): void
    {
        /** @var \Doctrine\DBAL\Connection $conn */
        $conn = self::getContainer()->get('doctrine')->getConnection();
        $conn->executeStatement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }
}
