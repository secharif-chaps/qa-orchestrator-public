<?php

namespace App\Tests\Units\Infrastructure\Shared;

use App\Domain\Shared\UpdatedByInterface;
use App\Domain\User\User;
use App\Infrastructure\Shared\UpdatedByDoctrineListener;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class UpdatedByDoctrineListenerTest extends TestCase
{
    private Security&MockObject $security;
    private UpdatedByDoctrineListener $listener;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->listener = new UpdatedByDoctrineListener($this->security);
    }

    public function testPreUpdateDoesNothingWhenEntityIsNotUpdatedByInterface(): void
    {
        $entity = new \stdClass();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);
        $this->security->expects($this->never())
            ->method('getUser');

        $this->listener->preUpdate($args);
    }

    public function testPreUpdateDoesNothingWhenNoUserIsAuthenticated(): void
    {
        $entity = $this->createMock(UpdatedByInterface::class);
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);
        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $entity->expects($this->never())
            ->method('setUpdatedBy');

        $this->listener->preUpdate($args);
    }

    public function testPreUpdateDoesNothingWhenAuthenticatedUserIsNotADomainUser(): void
    {
        $entity = $this->createMock(UpdatedByInterface::class);
        $nonDomainUser = $this->createStub(UserInterface::class);
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);

        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($nonDomainUser);
        $entity->expects($this->never())
            ->method('setUpdatedBy');

        $this->listener->preUpdate($args);
    }

    public function testPreUpdateSetsUpdatedByWhenConditionsAreMet(): void
    {
        $entity = $this->createMock(UpdatedByInterface::class);
        $user = new User();
        $args = $this->createMock(LifecycleEventArgs::class);
        $args->expects($this->once())
            ->method('getObject')
            ->willReturn($entity);
        $this->security->expects($this->once())
            ->method('getUser')
            ->willReturn($user);
        $entity->expects($this->once())
            ->method('setUpdatedBy')
            ->with($this->identicalTo($user));

        $this->listener->preUpdate($args);
    }
}
