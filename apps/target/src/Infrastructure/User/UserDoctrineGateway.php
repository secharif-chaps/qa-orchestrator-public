<?php

namespace App\Infrastructure\User;

use App\Domain\User\User;
use App\Domain\User\UserGatewayInterface;
use App\Domain\User\UserNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class UserDoctrineGateway implements UserGatewayInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function get(string $id): User
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);

        if (!$user instanceof User) {
            throw new UserNotFoundException('User not found for id ' . $id);
        }

        return $user;
    }

    public function getByEmail(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([
            'email' => $email,
        ]);

        if (!$user instanceof User) {
            throw new UserNotFoundException('User not found for email ' . $email);
        }

        return $user;
    }

    public function save(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
