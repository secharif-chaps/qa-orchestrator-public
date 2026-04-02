<?php

declare(strict_types=1);

namespace App\Infrastructure\Mercure;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Application\Mercure\GenerateTokenAction;
use App\Domain\User\User;
use App\UserInterface\Dto\Mercure\TokenDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @implements ProviderInterface<TokenDto>
 */
class MercureTokenProvider implements ProviderInterface
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly Security $security,
    ) {
        $this->messageBus = $messageBus;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TokenDto
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new NotFoundHttpException('User not found');
        }

        $userId = $user->getId();
        if (!\is_string($userId)) {
            throw new NotFoundHttpException('User not found');
        }

        $action = new GenerateTokenAction($userId);

        /** @var TokenDto $data */
        $data = $this->handle($action);

        return $data;
    }
}
