<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\Organisation;

use App\Application\Organisation\OrganisationSyncService;
use App\Domain\Organisation\Organisation;
use App\Domain\Organisation\TenantContext;
use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Infrastructure\Organisation\Doctrine\TenantFilter;
use App\Infrastructure\Organisation\EventSubscriber\TenantContextListener;
use App\Infrastructure\Organisation\Security\InternalJwtToken;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\FilterCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

#[CoversClass(TenantContextListener::class)]
class TenantContextListenerTest extends TestCase
{
    /** @var TokenStorageInterface&Stub */
    private TokenStorageInterface $tokenStorage;
    private TenantContext $tenantContext;

    /** @var OrganisationSyncService&Stub */
    private OrganisationSyncService $syncService;

    /** @var UserGatewayInterface&Stub */
    private UserGatewayInterface $userGateway;

    /** @var EntityManagerInterface&Stub */
    private EntityManagerInterface $entityManager;
    private TenantContextListener $listener;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createStub(TokenStorageInterface::class);
        $this->syncService = $this->createStub(OrganisationSyncService::class);
        $this->userGateway = $this->createStub(UserGatewayInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->tenantContext = new TenantContext();

        $tenantFilter = new TenantFilter($this->entityManager);
        $filterCollection = $this->createStub(FilterCollection::class);
        $filterCollection->method('enable')
->willReturn($tenantFilter);
        $this->entityManager->method('getFilters')
->willReturn($filterCollection);

        $this->rebuildListener();
    }

    public function testDoesNothingWhenNoToken(): void
    {
        $this->tokenStorage->method('getToken')
->willReturn(null);

        ($this->listener)($this->createControllerEvent());

        $this->expectNotToPerformAssertions();
    }

    public function testDoesNothingWhenUserNotInstanceOfUser(): void
    {
        $token = $this->createStub(PostAuthenticationToken::class);
        $token->method('getUser')
->willReturn(null);
        $this->tokenStorage->method('getToken')
->willReturn($token);

        ($this->listener)($this->createControllerEvent());

        $this->expectNotToPerformAssertions();
    }

    public function testSetsContextFromInternalJwtToken(): void
    {
        $user = new User('user-1', 'user@test.com', [], 'testuser');
        $organisation = new Organisation('Acme', 'org-uuid');

        $token = new InternalJwtToken($user, 'main', ['ROLE_USER'], [
            'sub' => 'user-1',
            'org_id' => 'org-uuid',
            'org_name' => 'Acme',
        ]);
        $this->tokenStorage->method('getToken')
->willReturn($token);
        $this->syncService->method('syncFromToken')
->willReturn($organisation);

        ($this->listener)($this->createControllerEvent());

        self::assertSame($organisation, $this->tenantContext->getCurrentOrganisation());
        self::assertSame($user, $this->tenantContext->getEffectiveUser());
        self::assertFalse($this->tenantContext->isImpersonating());
    }

    public function testSetsImpersonatorFromInternalJwtToken(): void
    {
        $user = new User('user-1', 'user@test.com', [], 'testuser');
        $admin = new User('admin-1', 'admin@test.com', [], 'admin');
        $organisation = new Organisation('Acme', 'org-uuid');

        $token = new InternalJwtToken($user, 'main', ['ROLE_USER'], [
            'sub' => 'user-1',
            'org_id' => 'org-uuid',
            'org_name' => 'Acme',
            'impersonator' => [
                'id' => 'admin-1',
                'username' => 'admin',
            ],
        ]);
        $this->tokenStorage->method('getToken')
->willReturn($token);
        $this->syncService->method('syncFromToken')
->willReturn($organisation);
        $this->userGateway->method('get')
->willReturn($admin);

        ($this->listener)($this->createControllerEvent());

        self::assertTrue($this->tenantContext->isImpersonating());
        self::assertSame($admin, $this->tenantContext->getImpersonator());
    }

    public function testSkipsWhenInternalJwtMissingOrgId(): void
    {
        $user = new User('user-1', 'user@test.com', [], 'testuser');

        $token = new InternalJwtToken($user, 'main', ['ROLE_USER'], [
            'sub' => 'user-1',
        ]);
        $this->tokenStorage->method('getToken')
->willReturn($token);

        $this->syncService = $this->createMock(OrganisationSyncService::class);
        $this->syncService->expects(self::never())->method('syncFromToken');
        $this->rebuildListener();

        ($this->listener)($this->createControllerEvent());
    }

    public function testKeycloakFallbackWithOrganizationClaim(): void
    {
        $user = new User('user-1', 'user@test.com', [], 'testuser');
        $organisation = new Organisation('my-org', 'my-org');

        $token = $this->createStub(PostAuthenticationToken::class);
        $token->method('getUser')
->willReturn($user);
        $this->tokenStorage->method('getToken')
->willReturn($token);

        $this->syncService->method('syncFromToken')
->willReturn($organisation);

        $jwtPayload = base64_encode(json_encode([
            'organization' => ['my-org'],
        ], \JSON_THROW_ON_ERROR));
        $fakeJwt = 'header.' . $jwtPayload . '.signature';

        $event = $this->createControllerEvent('Bearer ' . $fakeJwt);

        ($this->listener)($event);

        self::assertSame($organisation, $this->tenantContext->getCurrentOrganisation());
    }

    public function testKeycloakFallbackSkipsWhenNoOrgClaim(): void
    {
        $user = new User('user-1', 'user@test.com', [], 'testuser');

        $token = $this->createStub(PostAuthenticationToken::class);
        $token->method('getUser')
->willReturn($user);
        $this->tokenStorage->method('getToken')
->willReturn($token);

        $jwtPayload = base64_encode(json_encode([
            'sub' => 'user-1',
        ], \JSON_THROW_ON_ERROR));
        $fakeJwt = 'header.' . $jwtPayload . '.signature';

        $event = $this->createControllerEvent('Bearer ' . $fakeJwt);

        $this->syncService = $this->createMock(OrganisationSyncService::class);
        $this->syncService->expects(self::never())->method('syncFromToken');
        $this->rebuildListener();

        ($this->listener)($event);
    }

    private function createControllerEvent(?string $authorizationHeader = null): ControllerEvent
    {
        $request = Request::create('/api/test');
        if (null !== $authorizationHeader) {
            $request->headers->set('Authorization', $authorizationHeader);
        }

        $kernel = $this->createStub(HttpKernelInterface::class);

        return new ControllerEvent(
            $kernel,
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function rebuildListener(): void
    {
        $this->listener = new TenantContextListener(
            $this->tokenStorage,
            $this->syncService,
            $this->userGateway,
            $this->tenantContext,
            $this->entityManager,
            new NullLogger(),
        );
    }
}
