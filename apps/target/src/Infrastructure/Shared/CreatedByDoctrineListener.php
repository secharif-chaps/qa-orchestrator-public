<?php

namespace App\Infrastructure\Shared;

use App\Domain\Shared\CreatedByInterface;
use App\Domain\User\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Bundle\SecurityBundle\Security;

#[AsDoctrineListener(event: Events::prePersist)]
class CreatedByDoctrineListener
{
    public function __construct(
        private Security $security,
    ) {
    }

    /**
     * @param LifecycleEventArgs<CreatedByInterface> $args
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof CreatedByInterface || null !== $entity->getCreatedBy()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $entity->setCreatedBy($user);
    }
}
