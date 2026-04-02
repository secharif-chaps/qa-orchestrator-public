<?php

namespace App\Infrastructure\Shared;

use App\Domain\Shared\UpdatedByInterface;
use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::preUpdate)]
class UpdatedByDoctrineListener
{
    public function __construct(
        private readonly Security $security,
    ) {
    }

    /**
     * @param LifecycleEventArgs<UpdatedByInterface> $args
     *
     * @phpstan-ignore-next-line generics.notSubtype
     */
    public function preUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof UpdatedByInterface) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $entity->setUpdatedBy($user);
    }
}
