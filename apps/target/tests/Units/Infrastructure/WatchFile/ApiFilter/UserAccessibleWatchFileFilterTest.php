<?php

declare(strict_types=1);

namespace App\Tests\Units\Infrastructure\WatchFile\ApiFilter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use App\Domain\User\User;
use App\Domain\WatchFile\WatchFile;
use App\Infrastructure\WatchFile\ApiFilter\UserAccessibleWatchFileFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

class UserAccessibleWatchFileFilterTest extends TestCase
{
    private Security&Stub $security;
    private ManagerRegistry&Stub $managerRegistry;
    private EntityManagerInterface&Stub $entityManager;
    private QueryBuilder $queryBuilder;
    private QueryNameGeneratorInterface&Stub $queryNameGenerator;
    private UserAccessibleWatchFileFilter $filter;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->managerRegistry = $this->createStub(ManagerRegistry::class);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('hasField')
->willReturn(true);
        $classMetadata->method('hasAssociation')
->willReturn(false);

        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')
->willReturn($classMetadata);

        $this->managerRegistry->method('getManagerForClass')
->willReturn($this->entityManager);

        $this->queryBuilder = new QueryBuilder($this->entityManager);
        $this->queryBuilder->from(WatchFile::class, 'w');
        $this->queryBuilder->select('w');

        $this->queryNameGenerator = $this->createStub(QueryNameGeneratorInterface::class);

        $this->filter = new UserAccessibleWatchFileFilter($this->security, $this->managerRegistry);
    }

    public function testNoFilterAppliedWhenNoLoggedUser(): void
    {
        $this->security->method('getUser')
->willReturn(null);

        $this->filter->apply($this->queryBuilder, $this->queryNameGenerator, WatchFile::class);

        $this->assertStringNotContainsString('JOIN', $this->queryBuilder->getDQL());
    }

    public function testNoFilterAppliedForNonWatchFileResource(): void
    {
        $user = new User(id: 'user-id', email: 'user@test.com', roles: ['ROLE_USER']);
        $this->security->method('getUser')
->willReturn($user);

        $this->filter->apply($this->queryBuilder, $this->queryNameGenerator, \stdClass::class);

        $this->assertStringNotContainsString('JOIN', $this->queryBuilder->getDQL());
    }

    public function testRegularUserGetsInnerJoinFilter(): void
    {
        $user = new User(id: 'user-id', email: 'user@test.com', roles: ['ROLE_USER']);
        $this->security->method('getUser')
->willReturn($user);

        $this->filter->apply($this->queryBuilder, $this->queryNameGenerator, WatchFile::class);

        $dql = $this->queryBuilder->getDQL();
        $this->assertStringContainsString('JOIN', $dql);
        $this->assertStringContainsString('fu.user = :user', $dql);
    }

    public function testAdminWithRoleAdminSkipsFilter(): void
    {
        $user = new User(id: 'admin-id', email: 'admin@test.com', roles: ['ROLE_ADMIN']);
        $this->security->method('getUser')
->willReturn($user);

        $this->filter->apply($this->queryBuilder, $this->queryNameGenerator, WatchFile::class);

        $this->assertStringNotContainsString('JOIN', $this->queryBuilder->getDQL());
    }

    public function testAdminWithKeycloakRoleSkipsFilter(): void
    {
        $user = new User(id: 'admin-id', email: 'admin@test.com', roles: ['admin']);
        $this->security->method('getUser')
->willReturn($user);

        $this->filter->apply($this->queryBuilder, $this->queryNameGenerator, WatchFile::class);

        $this->assertStringNotContainsString('JOIN', $this->queryBuilder->getDQL());
    }

    public function testViewerRoleGetsInnerJoinFilter(): void
    {
        $user = new User(id: 'viewer-id', email: 'viewer@test.com', roles: ['organization.read']);
        $this->security->method('getUser')
->willReturn($user);

        $this->filter->apply($this->queryBuilder, $this->queryNameGenerator, WatchFile::class);

        $dql = $this->queryBuilder->getDQL();
        $this->assertStringContainsString('JOIN', $dql);
        $this->assertStringContainsString('fu.user = :user', $dql);
    }
}
