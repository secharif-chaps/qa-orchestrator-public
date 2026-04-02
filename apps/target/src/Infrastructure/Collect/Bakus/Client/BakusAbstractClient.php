<?php

namespace App\Infrastructure\Collect\Bakus\Client;

use App\Application\Collect\Auth\AuthenticateAction;
use App\Application\Collect\Auth\RefreshTokenAction;
use App\Domain\Collect\Auth\AccessToken;
use App\Domain\Collect\Exception\CollectException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

abstract class BakusAbstractClient
{
    use HandleTrait;
    private ?AccessToken $accessToken = null;

    public function __construct(
        private MessageBusInterface $messageBus,
    ) {
    }

    protected function getToken(): AccessToken
    {
        if (null === $this->accessToken) {
            $action = new AuthenticateAction('bakus');

            $accessToken = $this->handle($action);

            if (!$accessToken instanceof AccessToken) {
                throw new CollectException('Failed to retrieve access token from authentication action.');
            }

            $this->accessToken = $accessToken;
        }

        if ($this->accessToken->isExpiringSoon()) {
            $this->refreshToken();
        }

        if (null === $this->accessToken) {
            throw new CollectException('Access Token still not set after authentication.');
        }

        return $this->accessToken;
    }

    protected function refreshToken(): void
    {
        if (null === $this->accessToken) {
            throw new CollectException('Access token is not set. Please authenticate first.');
        }

        if ($this->accessToken->isExpiringSoon()) {
            $accessToken = $this->handle(new RefreshTokenAction('bakus'));

            if (!$accessToken instanceof AccessToken) {
                throw new CollectException('Failed to retrieve access token from authentication action.');
            }

            $this->accessToken = $accessToken;
        }
    }
}
