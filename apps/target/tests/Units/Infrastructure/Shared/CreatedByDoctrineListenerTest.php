<?php

namespace App\Tests\Units\Infrastructure\Shared;

use App\Domain\Shared\CreatedByInterface;
use App\Domain\User\User;
use App\Infrastructure\Shared\CreatedByDoctrineListener;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class CreatedByDoctrineListenerTest extends TestCase
{
    private Security&MockObject $security;
    private CreatedByDoctrineListener $listener;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->listener = new CreatedByDoctrineListener($this->security);
    }

    public function testPrePersistDoesNothingWhenEntityIsNotCreatedByInterface(): void
    {
        $entity = new \stdClass();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);
        $this->security->expects($this->never())
            ->method('getUser');

        $this->listener->prePersist($args);
    }

    public function testPrePersistDoesNothingWhenCreatedByIsAlreadySet(): void
    {
        $entity = $this->createMock(CreatedByInterface::class);
        $entity->expects($this->once())
            ->method('getCreatedBy')
            ->willReturn(new User());

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);

        $this->security->expects($this->never())
            ->method('getUser');

        $this->listener->prePersist($args);
    }

    public function testPrePersistDoesNothingWhenNoUserIsAuthenticated(): void
    {
        $entity = $this->createMock(CreatedByInterface::class);
        $entity->expects($this->once())
            ->method('getCreatedBy')
            ->willReturn(null);

        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);
        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $entity->expects($this->never())
            ->method('setCreatedBy');

        $this->listener->prePersist($args);
    }

    public function testPrePersistDoesNothingWhenAuthenticatedUserIsNotADomainUser(): void
    {
        $entity = $this->createMock(CreatedByInterface::class);
        $entity->expects($this->once())
            ->method('getCreatedBy')
            ->willReturn(null);

        $nonDomainUser = $this->createStub(
            UserInterface::class
        ); // A generic Symfony user, not our App\Domain\User\User
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($nonDomainUser);
        $entity->expects($this->never())
            ->method('setCreatedBy');

        $this->listener->prePersist($args);
    }

    public function testPrePersistSetsCreatedByWhenConditionsAreMet(): void
    {
        $entity = $this->createMock(CreatedByInterface::class);
        $entity->expects($this->once())
            ->method('getCreatedBy')
            ->willReturn(null);

        $user = new User();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);
        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $entity->expects($this->once())
            ->method('setCreatedBy')
            ->with($this->identicalTo($user));

        $this->listener->prePersist($args);
    }
}
